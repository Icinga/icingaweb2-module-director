<?php

namespace Icinga\Module\Director\Web\Controller;

use gipfl\Web\Widget\Hint;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchedObject;
use Icinga\Module\Director\Db\Branch\UuidLookup;
use Icinga\Module\Director\Deployment\DeploymentInfo;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Forms\CustomPropertiesForm;
use Icinga\Module\Director\Forms\DeploymentLinkForm;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Forms\IcingaCloneObjectForm;
use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Forms\ObjectPropertyForm;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaObjectGroup;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\Web\Controller\Extension\ObjectRestrictions;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\ObjectPreview;
use Icinga\Module\Director\Web\Table\ActivityLogTable;
use Icinga\Module\Director\Web\Table\BranchActivityTable;
use Icinga\Module\Director\Web\Table\GroupMemberTable;
use Icinga\Module\Director\Web\Table\IcingaObjectDatafieldTable;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use Icinga\Module\Director\Web\Widget\BranchedObjectHint;
use gipfl\IcingaWeb2\Link;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\FormElement\SubmitButtonElement;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class ObjectController extends ActionController
{
    use ObjectRestrictions;
    use BranchHelper;

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

    /** @var Session\SessionNamespace */
    protected Session\SessionNamespace $session;

    public function init()
    {
        $this->enableStaticObjectLoader($this->getTableName());
        if (! $this->getRequest()->isApiRequest()) {
            $this->loadOptionalObject();
        }
        parent::init();
        if ($this->getRequest()->isApiRequest()) {
            $this->initializeRestApi();
        } else {
            $this->session = Session::getSession()->getNamespace('director.variables');
            if (! $this->params->shift('_preserve_session')) {
                $this->session->delete('vars');
                $this->session->delete('added-properties');
                $this->session->delete('removed-properties');
            }

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
        $action = $this->getRequest()->getActionName();
        if (! ($action === 'variables' || $action === 'add-property')) {
            $this->session->delete('vars');
            $this->session->delete('added-properties');
            $this->session->delete('removed-properties');
        }

        if ($this->getRequest()->getActionName() === 'add-property') {
            return;
        }

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
            if ($this->showNotInBranch($this->translate('Creating Templates'))) {
                $this->addTitle($this->translate('Create a new Template'));
                return;
            }

            $this->addTemplate();
        } else {
            $this->addObject();
        }
        $branch = $this->getBranch();
        if (! $this->getRequest()->isApiRequest()) {
            $hasPreferred = $this->hasPreferredBranch();
            if ($branch->isBranch() || $hasPreferred) {
                $this->content()->add(new BranchedObjectHint($branch, $this->Auth(), null, $hasPreferred));
            }
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
        // Hint: Service Sets are 'templates' (as long as not being assigned to a host
        if (
            $this->getTableName() !== 'icinga_service_set'
            && $object->isTemplate()
            && $this->showNotInBranch($this->translate('Modifying Templates'))
        ) {
            return;
        }
        if ($object->isApplyRule() && $this->showNotInBranch($this->translate('Modifying Apply Rules'))) {
            return;
        }

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

        if (! $object instanceof IcingaServiceSet) {
            if ($object->isTemplate() && $this->showNotInBranch($this->translate('Cloning Templates'))) {
                return;
            }

            if ($object->isTemplate() && $this->showNotInBranch($this->translate('Cloning Apply Rules'))) {
                return;
            }
        }

        $form = IcingaCloneObjectForm::load()
            ->setBranch($this->getBranch())
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
        if ($this->showNotInBranch($this->translate('Managing Fields'))) {
            return;
        }

        try {
            $this->addFieldsFormAndTable($object, $type);
        } catch (NestingError $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }

    public function addPropertyAction()
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();
        $this->view->title = sprintf($this->translate('Add Custom Property: %s'), $this->object->getObjectName());
        $objectUuid = $this->object->get('uuid');

        $form = (new ObjectPropertyForm($this->db(), $object))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(ObjectPropertyForm::ON_SUCCESS, function (ObjectPropertyForm $form) use ($objectUuid) {
                $properties = $this->session->get('added-properties', []);
                $removedObjectProperties = $this->session->get('removed-properties', []);
                $propertyName = $form->getPropertyName();
                if (array_key_exists($propertyName, $removedObjectProperties)) {
                    unset($removedObjectProperties[$propertyName]);
                } elseif (! isset($properties[$propertyName])) {
                    $properties[$propertyName] = Uuid::fromString($form->getValue('property'))->getBytes();
                }

                $this->session->set('added-properties', $properties);
                $this->session->set('removed-properties', $removedObjectProperties);
                $this->redirectNow(Url::fromPath(
                    'director/' . $this->getType() . '/variables',
                    [
                        'uuid' => UUid::fromBytes($objectUuid)->toString(),
                        'items-added' => true,
                        '_preserve_session' => true
                    ]
                ));
            })
            ->handleRequest($this->getServerRequest());

        $this->content()->add($form);
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

    public function variablesAction(): void
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();

        $this->addTitle(
            $this->translate('Custom Variables: %s'),
            $object->getObjectName()
        );

        $this->prepareApplyForHeader();

        $objectProperties = $this->getObjectCustomProperties($object);
        if ($this->object->isTemplate()) {
            $this->actions()->add(
                (new ButtonLink(
                    $this->translate('Add Property'),
                    Url::fromPath(
                        'director/'. $this->getType() .'/add-property',
                        ['uuid' => $this->getUuidFromUrl(), '_preserve_session' => true]
                    )->getAbsoluteUrl(),
                    null,
                    ['class' => 'control-button']
                ))->openInModal()
            );
        }

        $form = $this->prepareCustomPropertiesForm($object, $objectProperties);
        if ($form) {
            $this->content()->add($form);
        }

        $this->tabs()->activate('variables');
    }

    public function prepareCustomPropertiesForm(
        IcingaObject $object,
        array $objectProperties = [],
        IcingaHost $host = null,
        IcingaService $appliedService = null,
        IcingaServiceSet $serviceSet = null,
        IcingaHost $inheritedServiceFrom = null
    ): ?CustomPropertiesForm {
        $hasAddedItems = $this->params->shift('items-added', false);
        $addedProperties = $this->session->get('added-properties');
        $removedProperties = $this->session->get('removed-properties');
        if (empty($objectProperties) && empty($addedProperties) && empty($removedProperties)) {
            $this->content()->add(Hint::info($this->translate('No custom properties defined.')));

            return null;
        }

        $isOverrideVars = $appliedService
            || $inheritedServiceFrom
            || ($host && $serviceSet);
        if ($this->session->get('vars')) {
            $vars = $this->session->get('vars');
            $storedVars = $vars;
        } else {
            if (! $isOverrideVars) {
                $vars = $object->getVars();
            } else {
                $vars = $host->getOverriddenServiceVars($object);
            }

            $storedVars = $vars;
            $vars = json_decode(json_encode($vars), true);

            $this->session->set('vars', $vars);
        }

        $inheritedVars = json_decode(json_encode($object->getInheritedVars()), JSON_OBJECT_AS_ARRAY);
        $origins = $object->getOriginsVars();
        $addedProperties = $this->session->get('added-properties');
        $removedProperties = $this->session->get('removed-properties');

        $hasChanges = json_encode((object) $vars) !== json_encode($storedVars)
            || ! empty($addedProperties)
            || ! empty($removedProperties);

        $result = [];
        foreach ($objectProperties as $row) {
            if (isset($vars[$row['key_name']])) {
                $row['value'] = $vars[$row['key_name']];
            }

            if (isset($inheritedVars[$row['key_name']])) {
                $row['inherited'] = $inheritedVars[$row['key_name']];
                $row['inherited_from'] = $origins->{$row['key_name']};
            }

            $result[] = $row;
        }

        $form = (new CustomPropertiesForm($object, $objectProperties, $hasAddedItems, $hasChanges));
        if ($host) {
            $form->setHostForService($host);
        }

        if ($appliedService) {
            $form->setApplyGenerated($appliedService);
        }

        if ($inheritedServiceFrom) {
            $form->setInheritedServiceFrom($inheritedServiceFrom);
        }

        if ($serviceSet) {
            $form->setServiceSet($serviceSet);
        }

        $form->on(CustomPropertiesForm::ON_SUCCESS, function () {
                $this->session->delete('vars');
                $this->session->delete('added-properties');
                $this->session->delete('removed-properties');
                $this->redirectNow(Url::fromRequest()->without('items-added'));
            })
            ->on(CustomPropertiesForm::ON_SENT, function (CustomPropertiesForm $form) use ($vars) {
                /** @var SubmitButtonElement $discard */
                $discard = $form->getElement('discard');
                if ($discard->hasBeenPressed()) {
                    $this->session->delete('vars');
                    $this->session->delete('added-properties');
                    $this->session->delete('removed-properties');
                    $this->redirectNow(Url::fromRequest()->without('items-added'));
                }

                /** @var Dictionary $propertiesElement */
                $propertiesElement = $form->getElement('properties');
                $vars = $propertiesElement->getDictionary();
                $this->session->set('vars', $vars);
            })
            ->handleRequest($this->getServerRequest());

        $form->load($result);

        return $form;
    }


    private function prepareApplyForHeader(): void
    {
        if (! ($this->object instanceof IcingaService) || $this->object->get('apply_for') === null) {
            return;
        }

        $applyFor = $this->object->get('apply_for');
        $fetchVar = $this->fetchVar(substr($applyFor, strlen('host.vars.')));
        if (empty($fetchVar)) {
            return;
        }

        $content = [];
        if ($fetchVar->value_type === 'dynamic-dictionary') {
            $dictionaryKeys = $this->fetchNestedDictionaryKeys($fetchVar->uuid);

            if (! empty($dictionaryKeys)) {
                $configVariables = new HtmlElement('ul', Attributes::create(['class' => 'nested-key-list']));
                foreach ($dictionaryKeys as $keyAttributes) {
                    if (str_contains($keyAttributes['key_name'], ' ')) {
                        continue;
                    }

                    $config = '$value.' . $keyAttributes['key_name'];
                    $content = [
                        new HtmlElement('div', null, Text::create(
                            $keyAttributes['label'] ?? $keyAttributes['key_name']
                        . ' ('
                        . $keyAttributes['key_name']
                        . ')'
                        )),
                        new HtmlElement('div', null, Text::create('=>'))
                    ];

                    if ($keyAttributes['value_type'] === 'fixed-dictionary') {
                        $nestedContent = [];

                        foreach ($this->fetchNestedDictionaryKeys($keyAttributes['uuid']) as $nestedKeyAttributes) {
                            if (str_contains($nestedKeyAttributes['key_name'], ' ')) {
                                continue;
                            }

                            $nestedConfig = $config . '.' . $nestedKeyAttributes['key_name'] . '$';
                            $nestedContent[] = new HtmlElement('div', null, Text::create($nestedConfig));

                            $nestedContent = [
                                new HtmlElement('div', null, Text::create(
                                    $nestedKeyAttributes['label'] ?? $nestedKeyAttributes['key_name']
                                . ' ('
                                . $nestedKeyAttributes['key_name']
                                . ')'
                                )),
                                new HtmlElement('div', null, Text::create('=>')),
                                new HtmlElement('div', null, Text::create(
                                    $nestedConfig
                                ))
                            ];
                        }

                        $content[] = new HtmlElement(
                            'div',
                            null,
                            new HtmlElement('div', null, Text::create(
                                '$value.'
                                . $keyAttributes['key_name']
                                . '$'
                            )),
                            new HtmlElement(
                                'ul',
                                null,
                                new HtmlElement('li', null, ...$nestedContent)
                            )
                        );
                    } else {
                        if (str_contains($keyAttributes['key_name'], ' ')) {
                            $config = '$value["' . $keyAttributes['key_name'] . '"]$';
                        } else {
                            $config = '$value.' . $keyAttributes['key_name'] . '$';
                        }

                        $content[] = new HtmlElement('div', null, Text::create($config));
                    }

                    $configVariables->addHtml(new HtmlElement('li', null, ...$content));
                }

                if (empty($content)) {
                    return;
                }

                $header = HtmlElement::create(
                    'div',
                    Attributes::create(['class' => ['apply-for-header-content']]),
                    [
                        Text::create($this->translate(
                            'Nested keys of selected host dictionary variable for apply-for-rule'
                            . ' are accessible through value as shown below:'
                        )),
                        $configVariables
                    ]
                );

                $this->content()->addHtml(new HtmlElement(
                    'div',
                    Attributes::create(['class' => ['apply-for-header']]),
                    $header
                ));
            }
        }
    }

    private function fetchNestedDictionaryKeys(string $dictionaryUuid)
    {
        $db = $this->db();
        $query = $db->getDbAdapter()
                          ->select()
                          ->from(
                              ['dp' => 'director_property'],
                              [
                                  'uuid' => 'dp.uuid',
                                  'key_name' => 'dp.key_name',
                                  'label' => 'dp.label',
                                  'value_type' => 'dp.value_type'
                              ]
                          )->where("parent_uuid = ?", $dictionaryUuid);

        return $db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);
    }

    protected function fetchVar(string $varName)
    {
        $db = $this->object->getConnection();
        $query = $db->select()
                    ->from(
                        ['dp' => 'director_property'],
                        ['*']
                    )
                    ->where('parent_uuid IS NULL AND key_name ', $varName);

        return $db->getDbAdapter()->fetchRow($query);
    }

    /**
     * Get custom properties for the host.
     *
     * @return array
     */
    protected function getObjectCustomProperties(IcingaObject $object, bool $isOverrideVars = false): array
    {
        if ($object->uuid === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $parents = $object->listAncestorIds();

        $uuids = [];
        $db = $this->db();
        foreach ($parents as $parent) {
            $uuids[] = IcingaObject::loadByType($type, $parent, $db)->get('uuid');
        }

        $objectUuid = $object->get('uuid');
        $uuids[] = $object->get('uuid');
        $query = $db->getDbAdapter()
                    ->select()
                    ->from(
                        ['dp' => 'director_property'],
                        [
                            'key_name' => 'dp.key_name',
                            'uuid' => 'dp.uuid',
                            $type . '_uuid' => 'iop.' . $type . '_uuid',
                            'value_type' => 'dp.value_type',
                            'label' => 'dp.label',
                            'children' => 'COUNT(cdp.uuid)'
                        ]
                    )
                    ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [])
                    ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                    ->where('iop.' . $type . '_uuid IN (?)', $uuids)
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                    ->order(
                        "FIELD(dp.value_type, 'string', 'number', 'bool', 'fixed-array',"
                        . " 'dynamic-array', 'fixed-dictionary', 'dynamic-dictionary')"
                    )
                    ->order('children')
                    ->order('key_name');

        $result = [];
        $removedProperties = $this->session->get('removed-properties', []);
        if ($isOverrideVars) {
            if ($object->isApplyRule()) {
                $serviceName = $object->getObjectName();
            } else {
                $serviceName = $this->params->getRequired('service');
            }

            $vars = json_decode(json_encode($this->object->getOverriddenServiceVars($serviceName)), true);
        } else {
            $vars = json_decode(json_encode($object->getVars()), true);
        }

        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            if ($objectUuid === $row[$type . '_uuid']) {
                $row['allow_removal'] = true;
            } else {
                $row['allow_removal'] = false;
            }

            if (isset($vars[$row['key_name']])) {
                $row['value'] = $vars[$row['key_name']];
            }

            if (array_key_exists($row['key_name'], $removedProperties)) {
                $row['removed'] = true;
            }

            $result[$row['key_name']] = $row;
        }

        $addedProperties = $this->session->get('added-properties');
        if ($addedProperties) {
            $query = $db->getDbAdapter()
                        ->select()
                        ->from(
                            ['dp' => 'director_property'],
                            [
                                'key_name' => 'dp.key_name',
                                'uuid' => 'dp.uuid',
                                'value_type' => 'dp.value_type',
                                'label' => 'dp.label',
                                'children' => 'COUNT(cdp.uuid)'
                            ]
                        )
                        ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                        ->where('dp.' . 'uuid IN (?)', $addedProperties)
                        ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                        ->order(
                            "FIELD(dp.value_type, 'string', 'number', 'bool', 'fixed-array',"
                            . " 'dynamic-array', 'fixed-dictionary', 'dynamic-dictionary')"
                        )
                        ->order('children')
                        ->order('key_name');

            foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
                $row['allow_removal'] = true;
                $row['host_uuid'] = $this->object->get('uuid');
                if (! isset($result[$row['key_name']])) {
                    $row['new'] = true;
                }

                if (isset($vars[$row['key_name']])) {
                    $row['value'] = $vars[$row['key_name']];
                }

                $result[$row['key_name']] = $row;
            }
        }

        return $result;
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
        $this->showOptionalBranchActivity($table);
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

        $this->object = $this->loadSpecificObject($this->getTableName(), $this->getUuidFromUrl(), true);
    }

    protected function loadSpecificObject($tableName, $key, $showHint = false)
    {
        $branch = $this->getBranch();
        $branchedObject = BranchedObject::load($this->db(), $tableName, $key, $branch);
        $object = $branchedObject->getBranchedDbObject($this->db());
        assert($object instanceof IcingaObject);
        $object->setBeingLoadedFromDb();
        if (! $this->allowsObject($object)) {
            throw new NotFoundError('No such object available');
        }
        if ($showHint) {
            $hasPreferredBranch = $this->hasPreferredBranch();
            if (
                ($hasPreferredBranch || $branch->isBranch())
                && $object->isObject()
                && ! $this->getRequest()->isApiRequest()
            ) {
                $this->content()->add(
                    new BranchedObjectHint($branch, $this->Auth(), $branchedObject, $hasPreferredBranch)
                );
            }
        }

        return $object;
    }

    protected function requireUuid($key)
    {
        if (! $key instanceof UuidInterface) {
            $key = UuidLookup::findUuidForKey($key, $this->getTableName(), $this->db(), $this->getBranch());
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
                if ($this->getBranch()->isBranch()) {
                    $this->actions()->add($this->linkToMergeBranch($this->getBranch()));
                } else {
                    $this->actions()->add(
                        DeploymentLinkForm::create(
                            $this->db(),
                            $info,
                            $this->Auth(),
                            $this->api()
                        )->handleRequest()
                    );
                }
            }
        } catch (IcingaException $e) {
            // pass (deployment may not be set up yet)
        }
    }

    protected function linkToMergeBranch(Branch $branch)
    {
        $link = Branch::requireHook()->linkToBranch($branch, $this->Auth(), $this->translate('Merge'));
        if ($link instanceof Link) {
            $link->addAttributes(['class' => 'icon-flapping']);
        }

        return $link;
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

    protected function addObjectForm(IcingaObject $object = null)
    {
        $form = $this->loadObjectForm($object);
        $this->content()->add($form);
        $form->handleRequest();
        return $this;
    }

    protected function loadObjectForm(IcingaObject $object = null)
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
        if (true || $form->supportsBranches()) {
            $form->setBranch($this->getBranch());
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

    protected function showOptionalBranchActivity($activityTable)
    {
        $branch = $this->getBranch();
        if ($branch->isBranch() && (int) $this->params->get('page', '1') === 1) {
            $table = new BranchActivityTable($branch->getUuid(), $this->db(), $this->object->getUniqueId());
            if (count($table) > 0) {
                $this->content()->add(Hint::info(Html::sprintf($this->translate(
                    'The following modifications are visible in this %s only...'
                ), Branch::requireHook()->linkToBranch(
                    $branch,
                    $this->Auth(),
                    $this->translate('configuration branch')
                ))));
                $this->content()->add($table);
                if (count($activityTable) === 0) {
                    return;
                }
                $this->content()->add(Html::tag('br'));
                $this->content()->add(Hint::ok($this->translate(
                    '...and the modifications below are already in the main branch:'
                )));
                $this->content()->add(Html::tag('br'));
            }
        }
    }
}
