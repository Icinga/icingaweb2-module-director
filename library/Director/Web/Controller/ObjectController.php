<?php

namespace Icinga\Module\Director\Web\Controller;

use gipfl\Web\Widget\Hint;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Dashboard\Dashlet\DeploymentDashlet;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\UuidLookup;
use Icinga\Module\Director\Deployment\DeploymentInfo;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Forms\DeploymentLinkForm;
use Icinga\Module\Director\Forms\IcingaCloneObjectForm;
use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaObjectGroup;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\Web\Controller\Extension\ObjectRestrictions;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\ObjectPreview;
use Icinga\Module\Director\Web\Table\ActivityLogTable;
use Icinga\Module\Director\Web\Table\GroupMemberTable;
use Icinga\Module\Director\Web\Table\IcingaObjectDatafieldTable;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use gipfl\IcingaWeb2\Link;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class ObjectController extends ActionController
{
    use ObjectRestrictions;

    /** @var IcingaObject */
    protected $object;

    /** @var bool This controller handles REST API requests */
    protected $isApified = true;

    /** @var array Allowed object types we are allowed to edit anyways */
    protected $allowedExternals = array(
        'apiuser',
        'endpoint'
    );

    protected $type;

    /** @var string|null */
    protected $objectBaseUrl;

    public function init()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->loadOptionalObject();
        }
        parent::init();
        if ($this->getRequest()->isApiRequest()) {
            $this->initializeRestApi();
        } else {
            $this->initializeWebRequest();
        }
    }

    protected function initializeRestApi()
    {
        $handler = new IcingaObjectHandler($this->getRequest(), $this->getResponse(), $this->db());
        try {
            $this->loadOptionalObject();
        } catch (NotFoundError $e) {
            // Silently ignore the error, the handler will complain
            $handler->sendJsonError($e, 404);
            // TODO: nice shutdown
            exit;
        }

        $handler->setApi($this->api());
        if ($this->object) {
            $handler->setObject($this->object);
        }
        $handler->dispatch();
        // Hint: also here, hard exit. There is too much magic going on.
        // Letting this bubble up smoothly would be "correct", but proved
        // to be too fragile. Web 2, all kinds of pre/postDispatch magic,
        // different view renderers - hard exit is the only safe bet right
        // now.
        exit;
    }

    protected function initializeWebRequest()
    {
        if ($this->getRequest()->getActionName() === 'add') {
            $this->addSingleTab(
                sprintf($this->translate('Add %s'), ucfirst($this->getType())),
                null,
                'add'
            );
        } else {
            $this->tabs(new ObjectTabs(
                $this->getRequest()->getControllerName(),
                $this->getAuth(),
                $this->object
            ));
        }
        if ($this->object !== null) {
            $this->addDeploymentLink();
        }
    }

    /**
     * @throws NotFoundError
     */
    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->redirectToPreviewForExternals()
                ->editAction();
        }
    }

    public function addAction()
    {
        $this->tabs()->activate('add');
        $url = sprintf('director/%ss', $this->getPluralType());

        $imports = $this->params->get('imports');
        $form = $this->loadObjectForm()
            ->presetImports($imports)
            ->setSuccessUrl($url);

        if ($oType = $this->params->get('type', 'object')) {
            $form->setPreferredObjectType($oType);
        }
        if ($oType === 'template') {
            $this->addTemplate();
        } else {
            $this->addObject();
        }

        $form->handleRequest();
        $this->content()->add($form);
    }

    /**
     * @throws NotFoundError
     */
    public function editAction()
    {
        $object = $this->requireObject();
        $this->tabs()->activate('modify');
        $this->addObjectTitle();

        $this->addObjectForm($object)
             ->addActionClone()
             ->addActionUsage()
             ->addActionBasket();
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Security\SecurityException
     */
    public function renderAction()
    {
        $this->assertTypePermission()
             ->assertPermission('director/showconfig');
        $this->tabs()->activate('render');
        $preview = new ObjectPreview($this->requireObject(), $this->getRequest());
        if ($this->object->isExternal()) {
            $this->addActionClone();
        }
        $this->addActionBasket();
        $preview->renderTo($this);
    }

    /**
     * @throws NotFoundError
     */
    public function cloneAction()
    {
        $this->assertTypePermission();
        $object = $this->requireObject();
        $this->addTitle($this->translate('Clone: %s'), $object->getObjectName())
            ->addBackToObjectLink();

        $form = IcingaCloneObjectForm::load()
            ->setObject($object)
            ->setObjectBaseUrl($this->getObjectBaseUrl())
            ->handleRequest();

        if ($object->isExternal()) {
            $this->tabs()->activate('render');
        } else {
            $this->tabs()->activate('modify');
        }
        $this->content()->add($form);
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Security\SecurityException
     */
    public function fieldsAction()
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();
        $type = $this->getType();

        $this->addTitle(
            $this->translate('Custom fields: %s'),
            $object->getObjectName()
        );
        $this->tabs()->activate('fields');

        try {
            $this->addFieldsFormAndTable($object, $type);
        } catch (NestingError $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }

    protected function addFieldsFormAndTable($object, $type)
    {
        $form = IcingaObjectFieldForm::load()
            ->setDb($this->db())
            ->setIcingaObject($object);

        if ($id = $this->params->get('field_id')) {
            $form->loadObject([
                "{$type}_id"   => $object->id,
                'datafield_id' => $id
            ]);

            $this->actions()->add(Link::create(
                $this->translate('back'),
                $this->url()->without('field_id'),
                null,
                ['class' => 'icon-left-big']
            ));
        }
        $form->handleRequest();
        $this->content()->add($form);
        $table = new IcingaObjectDatafieldTable($object);
        $table->getAttributes()->set('data-base-target', '_self');
        $table->renderTo($this);
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Security\SecurityException
     */
    public function historyAction()
    {
        $this
            ->assertTypePermission()
            ->assertPermission('director/audit')
            ->setAutorefreshInterval(10)
            ->tabs()->activate('history');

        $name = $this->requireObject()->getObjectName();
        $this->addTitle($this->translate('Activity Log: %s'), $name);

        $db = $this->db();
        $objectTable = $this->object->getTableName();
        $table = (new ActivityLogTable($db))
            ->setLastDeployedId($db->getLastDeploymentActivityLogId())
            ->filterObject($objectTable, $name);
        if ($host = $this->params->get('host')) {
            $table->filterHost($host);
        }
        $table->renderTo($this);
    }

    /**
     * @throws NotFoundError
     */
    public function membershipAction()
    {
        $object = $this->requireObject();
        if (! $object instanceof IcingaObjectGroup) {
            throw new NotFoundError('Not Found');
        }

        $this
            ->addTitle($this->translate('Group membership: %s'), $object->getObjectName())
            ->setAutorefreshInterval(15)
            ->tabs()->activate('membership');

        $type = substr($this->getType(), 0, -5);
        GroupMemberTable::create($type, $this->db())
            ->setGroup($object)
            ->renderTo($this);
    }

    /**
     * @return $this
     * @throws NotFoundError
     */
    protected function addObjectTitle()
    {
        $object = $this->requireObject();
        $name = $object->getObjectName();
        if ($object->isTemplate()) {
            $this->addTitle($this->translate('Template: %s'), $name);
        } else {
            $this->addTitle($name);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws NotFoundError
     */
    protected function addActionUsage()
    {
        $type = $this->getType();
        $object = $this->requireObject();
        if ($object->isTemplate() && $type !== 'serviceSet') {
            $this->actions()->add([
                Link::create(
                    $this->translate('Usage'),
                    "director/{$type}template/usage",
                    ['name'  => $object->getObjectName()],
                    ['class' => 'icon-sitemap']
                )
            ]);
        }

        return $this;
    }

    protected function addActionClone()
    {
        $this->actions()->add(Link::create(
            $this->translate('Clone'),
            $this->getObjectBaseUrl() . '/clone',
            $this->object->getUrlParams(),
            array('class' => 'icon-paste')
        ));

        return $this;
    }

    /**
     * @return $this
     */
    protected function addActionBasket()
    {
        if ($this->hasBasketSupport()) {
            $object = $this->object;
            if ($object instanceof ExportInterface) {
                if ($object instanceof IcingaCommand) {
                    if ($object->isExternal()) {
                        $type = 'ExternalCommand';
                    } elseif ($object->isTemplate()) {
                        $type = 'CommandTemplate';
                    } else {
                        $type = 'Command';
                    }
                } elseif ($object instanceof IcingaServiceSet) {
                    $type = 'ServiceSet';
                } elseif ($object->isTemplate()) {
                    $type = ucfirst($this->getType()) . 'Template';
                } elseif ($object->isGroup()) {
                    $type = ucfirst($this->getType());
                } else {
                    // Command? Sure?
                    $type = ucfirst($this->getType());
                }
                $this->actions()->add(Link::create(
                    $this->translate('Add to Basket'),
                    'director/basket/add',
                    [
                        'type'  => $type,
                        'names' => $object->getUniqueIdentifier()
                    ],
                    ['class' => 'icon-tag']
                ));
            }
        }

        return $this;
    }

    protected function addTemplate()
    {
        $this->assertPermission('director/admin');
        $this->addTitle(
            $this->translate('Add new Icinga %s template'),
            $this->getTranslatedType()
        );
    }

    protected function addObject()
    {
        $this->assertTypePermission();
        $imports = $this->params->get('imports');
        if (is_string($imports) && strlen($imports)) {
            $this->addTitle(
                $this->translate('Add %s: %s'),
                $this->getTranslatedType(),
                $imports
            );
        } else {
            $this->addTitle(
                $this->translate('Add new Icinga %s'),
                $this->getTranslatedType()
            );
        }
    }

    protected function redirectToPreviewForExternals()
    {
        if (
            $this->object
            && $this->object->isExternal()
            && ! in_array($this->object->getShortTableName(), $this->allowedExternals)
        ) {
            $this->redirectNow(
                $this->getRequest()->getUrl()->setPath(sprintf('director/%s/render', $this->getType()))
            );
        }

        return $this;
    }

    protected function getType()
    {
        if ($this->type === null) {
            // Strip final 's' and upcase an eventual 'group'
            $this->type = preg_replace(
                array('/group$/', '/period$/', '/argument$/', '/apiuser$/', '/set$/'),
                array('Group', 'Period', 'Argument', 'ApiUser', 'Set'),
                $this->getRequest()->getControllerName()
            );
        }

        return $this->type;
    }

    protected function getPluralType()
    {
        return $this->getType() . 's';
    }

    protected function getTranslatedType()
    {
        return $this->translate(ucfirst($this->getType()));
    }

    protected function assertTypePermission()
    {
        $type = strtolower($this->getPluralType());
        // TODO: Check getPluralType usage, fix it there.
        if ($type === 'scheduleddowntimes') {
            $type = 'scheduled-downtimes';
        }

        return $this->assertPermission("director/$type");
    }

    protected function loadOptionalObject()
    {
        if ($this->params->get('uuid') || null !== $this->params->get('name') || $this->params->get('id')) {
            $this->loadObject();
        }
    }

    /**
     * @return ?UuidInterface
     * @throws InvalidPropertyException
     * @throws NotFoundError
     */
    protected function getUuidFromUrl()
    {
        $key = null;
        if ($uuid = $this->params->get('uuid')) {
            $key = Uuid::fromString($uuid);
        } elseif ($id = $this->params->get('id')) {
            $key = (int) $id;
        } elseif (null !== ($name = $this->params->get('name'))) {
            $key = $name;
        }
        if ($key === null) {
            $request = $this->getRequest();
            if ($request->isApiRequest() && $request->isGet()) {
                $this->getResponse()->setHttpResponseCode(422);

                throw new InvalidPropertyException(
                    'Cannot load object, missing parameters'
                );
            }

            return null;
        }

        return $this->requireUuid($key);
    }

    protected function loadObject()
    {
        if ($this->object) {
            throw new ProgrammingError('Loading an object twice is not very efficient');
        }

        $this->object = $this->loadSpecificObject($this->getTableName(), $this->getUuidFromUrl());
    }

    protected function loadSpecificObject($tableName, $key)
    {
        $class = DbObjectTypeRegistry::classByType($tableName);
        $object = $class::loadWithUniqueId($key, $this->db());
        if ($object === null) {
            throw new NotFoundError('Not found');
        }
        assert($object instanceof IcingaObject);
        $object->setBeingLoadedFromDb();
        if (! $this->allowsObject($object)) {
            throw new NotFoundError('No such object available');
        }

        return $object;
    }

    protected function requireUuid($key)
    {
        if (! $key instanceof UuidInterface) {
            $key = UuidLookup::findUuidForKey($key, $this->getTableName(), $this->db());
            if ($key === null) {
                throw new NotFoundError('No such object available');
            }
        }

        return $key;
    }

    protected function getTableName()
    {
        return DbObjectTypeRegistry::tableNameByType($this->getType());
    }

    protected function addDeploymentLink()
    {
        try {
            $info = new DeploymentInfo($this->db());
            $info->setObject($this->object);

            if (! $this->getRequest()->isApiRequest()) {
                $this->actions()->add(
                    DeploymentLinkForm::create(
                        $this->db(),
                        $info,
                        $this->Auth(),
                        $this->api()
                    )
                        ->callOnSuccess(function () {
                            $this->getResponse()->setHeader('X-Icinga-Extra-Updates', '#col1');
                        })
                        ->handleRequest()
                );

                if (
                    DirectorDeploymentLog::hasDeployments($this->db())
                    && (new DeploymentDashlet($this->db()))->lastDeploymentPending()
                ) {
                    $this->actions()->prependHtml(
                        Hint::warning($this->translate(
                            'There is an active deployment running, please wait until it is finished'
                            . ' before creating a new deployment.'
                        ))
                    );
                }
            }
        } catch (IcingaException $e) {
            // pass (deployment may not be set up yet)
        }
    }

    protected function addBackToObjectLink()
    {
        $params = [
            'uuid' => $this->object->getUniqueId()->toString(),
        ];

        if ($this->object instanceof IcingaService) {
            if (($host = $this->object->get('host')) !== null) {
                $params['host'] = $host;
            } elseif (($set = $this->object->get('service_set')) !== null) {
                $params['set'] = $set;
            }
        }

        $this->actions()->add(Link::create(
            $this->translate('back'),
            $this->getObjectBaseUrl(),
            $params,
            ['class' => 'icon-left-big']
        ));

        return $this;
    }

    protected function addObjectForm(?IcingaObject $object = null)
    {
        $form = $this->loadObjectForm($object);
        $this->content()->add($form);
        $form->handleRequest();
        return $this;
    }

    protected function loadObjectForm(?IcingaObject $object = null)
    {
        /** @var DirectorObjectForm $class */
        $class = sprintf(
            'Icinga\\Module\\Director\\Forms\\Icinga%sForm',
            ucfirst($this->getType())
        );

        $form = $class::load()
            ->setDb($this->db())
            ->setAuth($this->Auth());

        if ($object !== null) {
            $form->setObject($object);
        }

        $this->onObjectFormLoaded($form);

        return $form;
    }

    protected function getObjectBaseUrl()
    {
        return $this->objectBaseUrl ?: 'director/' . strtolower($this->getType());
    }

    protected function hasBasketSupport()
    {
        return $this->object->isTemplate() || $this->object->isGroup();
    }

    protected function onObjectFormLoaded(DirectorObjectForm $form)
    {
    }

    /**
     * @return IcingaObject
     * @throws NotFoundError
     */
    protected function requireObject()
    {
        if (! $this->object) {
            $this->getResponse()->setHttpResponseCode(404);
            if (null === $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }

        return $this->object;
    }
}
