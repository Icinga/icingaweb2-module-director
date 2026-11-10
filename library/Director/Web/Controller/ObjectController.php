<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Controller;

use gipfl\Web\Widget\Hint;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\CustomVariable\CustomPropertyPlacement;
use Icinga\Module\Director\CustomVariable\CustomPropertySource;
use Icinga\Module\Director\CustomVariable\CustomPropertySourceKind;
use Icinga\Module\Director\CustomVariable\CustomPropertySources;
use Icinga\Module\Director\Dashboard\Dashlet\DeploymentDashlet;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchedObject;
use Icinga\Module\Director\Db\Branch\UuidLookup;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Deployment\DeploymentInfo;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Forms\DeploymentLinkForm;
use Icinga\Module\Director\Forms\IcingaCloneObjectForm;
use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Forms\ObjectCustomvarForm;
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
use Icinga\Web\Form;
use Icinga\Web\Notification;
use ipl\Html\Attributes;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Html\ValidHtml;
use ipl\Web\Compat\Multipart;
use ipl\Web\Compat\ViewRenderer;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
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

    /** @var Multipart[] */
    protected array $parts = [];

    /** @var array<int, CustomPropertySource[]> Sources of the objects shown, by object id */
    protected array $customPropertySources = [];

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
            $this->initializeWebRequest();
        }
    }

    protected function initializeRestApi()
    {
        if ($this->getRequest()->getActionName() === 'variables') {
            // the whole variables endpoint stays admin only over the API
            $this->assertPermission('director/admin');
        }

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
        if ($action === 'add-var') {
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

    public function postDispatch(): void
    {
        $document = new HtmlDocument();
        if (! empty($this->parts)) {
            $partSeparator = base64_encode(random_bytes(16));
            $this->getResponse()
                ->setHeader('X-Icinga-Multipart-Content', $partSeparator);
            $document->setSeparator("\n$partSeparator\n");
            $document->add($this->parts);
            // content and controls of the controller view property must be set to null,
            // so that the SimpleViewRenderer is not called in ActionController
            $this->view->content = null;
            $this->view->controls = null;
        } else {
            if (! $this->content()->isEmpty()) {
                $document->prepend($this->content());

                if (! $this->view->compact && ! $this->controls()->isEmpty()) {
                    $document->prepend($this->controls());
                }
            }
        }

        ViewRenderer::inject();

        $this->view->document = $document;

        parent::postDispatch();
    }

    /**
     * Add a part to be served as multipart-content
     *
     * @param ValidHtml $content
     * @param string    $id
     *
     * @return $this
     */
    private function addPart(ValidHtml $content, string $id): static
    {
        $part = new Multipart();
        $part->add($content);
        $this->parts[] = $part->setFor($id);

        return $this;
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

    /**
     * Show the add-a-variable modal and handle its submit
     *
     * Carries the added and required var uuids and the names of the rows
     * removed on screen along on the redirect, so the variables tab still
     * knows about them after the reload
     */
    public function addVarAction(): void
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();
        $this->assertDefinesCustomProperties($object);
        $this->view->title = sprintf($this->translate('Add Custom Variable: %s'), $this->object->getObjectName());

        $addedVarUuids = $this->params->getValues('addedVarUuids');
        $requiredVarUuids = $this->params->getValues('requiredVarUuids');
        $removedVars = $this->params->getValues('removedVars');
        $nextSlotIndex = (int) $this->params->shift('nextSlotIndex');

        $form = (new ObjectCustomvarForm($this->db(), $object, $addedVarUuids))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(ObjectCustomvarForm::ON_SUBMIT, function (ObjectCustomvarForm $form) use (
                $object,
                $addedVarUuids,
                $requiredVarUuids,
                $removedVars,
                $nextSlotIndex
            ) {
                $newUuid = $form->getValue('property');
                if (! in_array($newUuid, $addedVarUuids, true)) {
                    $addedVarUuids[] = $newUuid;
                }

                if ($form->isRequired() && ! in_array($newUuid, $requiredVarUuids, true)) {
                    $requiredVarUuids[] = $newUuid;
                }

                $redirectUrl = Url::fromPath(
                    'director/' . $this->getType() . '/variables',
                    [
                        'uuid'           => Uuid::fromBytes($object->get('uuid'))->toString(),
                        'newVarUuid'    => $newUuid,
                        'nextSlotIndex' => $nextSlotIndex
                    ]
                );
                $redirectUrl->getParams()->addValues('addedVarUuids', $addedVarUuids);
                $redirectUrl->getParams()->addValues('requiredVarUuids', $requiredVarUuids);
                $redirectUrl->getParams()->addValues('removedVars', $removedVars);

                $this->redirectNow($redirectUrl);
            })
            ->handleRequest($this->getServerRequest());

        $this->content()->add($form);
    }

    /**
     * Make sure custom variables can be added to the given object directly
     *
     * @param IcingaObject $object
     *
     * @return void
     *
     * @throws NotFoundError
     */
    private function assertDefinesCustomProperties(IcingaObject $object): void
    {
        if (! $object->definesCustomProperties()) {
            throw new NotFoundError('Custom variables can not be added to this object');
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
     * Show the Custom Variables tab and handle saving it
     *
     * Also picks up the added and required var uuids passed along by
     * addVarAction, so a var added there still shows up after the redirect
     */
    public function variablesAction(): void
    {
        // attach is gated separately in CustomVariablesForm::assertCanAttachNewVariable()
        $object = $this->requireObject();

        $newVarUuid = $this->params->shift('newVarUuid');
        $nextSlotIndex = (int) $this->params->shift('nextSlotIndex');
        $removedVars = $this->params->getValues('removedVars');

        $addedVarUuids = array_unique(array_merge(
            $this->params->getValues('addedVarUuids'),
            array_filter(explode(',', $this->getRequest()->getPost('addedVarUuids', '')))
        ));

        $requiredVarUuids = array_unique(array_merge(
            $this->params->getValues('requiredVarUuids'),
            array_filter(explode(',', $this->getRequest()->getPost('requiredVarUuids', '')))
        ));

        $form = $this->prepareCustomPropertiesForm($object, null, $addedVarUuids, $requiredVarUuids);

        $form
            ->on(
                CustomVariablesForm::ON_SUBMIT,
                function (CustomVariablesForm $form) {
                    if ($form->varsHasBeenModified()) {
                        Notification::success(
                            sprintf(
                                $this->translate('Custom variables have been successfully saved for %s'),
                                $form->object->getObjectName(),
                            )
                        );
                    } else {
                        Notification::success($this->translate('There is nothing to change.'));
                    }

                    $this->redirectNow(Url::fromRequest()->without(
                        ['addedVarUuids', 'requiredVarUuids', 'removedVars', 'newVarUuid', 'nextSlotIndex']
                    ));
                }
            )->on(
                CustomVariablesForm::ON_REQUEST,
                function (
                    ServerRequestInterface $request,
                    CustomVariablesForm $form
                ) use (
                    $object,
                    $newVarUuid,
                    $nextSlotIndex,
                    $addedVarUuids,
                    $requiredVarUuids,
                    $removedVars
                ) {
                    if ($newVarUuid === null) {
                        return;
                    }

                    $this->sendNewVarMultipartUpdate(
                        $object,
                        $form,
                        $newVarUuid,
                        $nextSlotIndex,
                        $addedVarUuids,
                        $requiredVarUuids,
                        $removedVars
                    );
                    $this->params->remove('addedVarUuids');
                    $this->params->remove('requiredVarUuids');
                    $this->params->remove('removedVars');
                    $this->getResponse()->setHeader('X-Icinga-Location-Query', $this->params->toString());
                }
            )->handleRequest($this->getServerRequest());

        if ($newVarUuid !== null) {
            return;
        }

        $this->prepareApplyForHeader();

        if ($object->definesCustomProperties() && $this->hasPermission(Permission::ADMIN)) {
            /** @var \Icinga\Module\Director\Forms\DictionaryElements\Dictionary $dictionary */
            $dictionary = $form->getElement('properties');
            $slotIndex = $dictionary->getItemCount();

            $buttonUrl = Url::fromPath(
                'director/' . $this->getType() . '/add-var',
                ['uuid' => $this->getUuidFromUrl(), 'nextSlotIndex' => $slotIndex]
            );
            $buttonUrl->getParams()->addValues('addedVarUuids', $addedVarUuids);
            $buttonUrl->getParams()->addValues('requiredVarUuids', $requiredVarUuids);
            // rows removed on screen still have their place in the rows, but not in the form
            $buttonUrl->getParams()->addValues('removedVars', $dictionary->getItemsToRemove());

            $this->actions()->add(
                Html::tag('div', ['id' => 'add-custom-var-button', 'class' => 'add-custom-var-button'], [
                    (new ButtonLink(
                        $this->translate('Add Custom Variable'),
                        $buttonUrl->getAbsoluteUrl(),
                        null,
                        ['class' => 'control-button']
                    ))->openInModal()
                ])
            );
        }

        $this->content()->add($form);

        $this->addTitle(
            $this->translate('Custom Variables: %s'),
            $object->getObjectName()
        );

        $this->tabs()->activate('variables');
    }

    /**
     * Send a multipart update for the new custom variable.
     *
     * The new row lands in the object's own box. When the object already
     * reaches the variable through a template or its check command, the new
     * row takes the index of the inherited row and the inherited row is
     * emptied, see {@see getObjectCustomProperties()}.
     *
     * @param IcingaObject        $object
     * @param CustomVariablesForm $form
     * @param string              $newVarUuid
     * @param int                 $nextSlotIndex    Counter for the placeholders new rows are inserted into
     * @param array               $addedVarUuids
     * @param array               $requiredVarUuids
     * @param string[]            $removedVars      Names of the rows removed on screen, they take no index
     *
     * @return void
     */
    private function sendNewVarMultipartUpdate(
        IcingaObject $object,
        CustomVariablesForm $form,
        string $newVarUuid,
        int $nextSlotIndex,
        array $addedVarUuids,
        array $requiredVarUuids = [],
        array $removedVars = []
    ): void {
        if (! $this->hasPermission(Permission::ADMIN)) {
            return;
        }

        $type = $object->getShortTableName();
        $db = $this->db()->getDbAdapter();
        $uuidBytes = Uuid::fromString($newVarUuid)->getBytes();

        $rows = array_diff_key(
            $this->getObjectCustomProperties($object, false, $addedVarUuids, $requiredVarUuids),
            array_flip($removedVars)
        );
        $index = 0;
        $propertyData = null;
        foreach ($rows as $row) {
            if ($row['uuid'] === $uuidBytes) {
                $propertyData = $row;

                break;
            }

            $index++;
        }

        if ($propertyData === null || empty($propertyData['new'])) {
            return;
        }

        $isTakeOver = isset($this->getObjectCustomProperties($object)[$propertyData['key_name']]);
        unset($propertyData['value']);
        $propertyData[$type . '_uuid'] = $object->get('uuid');

        if (isset($object->vars()->{$propertyData['key_name']})) {
            // Normalize via JSON round-trip (same as getObjectCustomProperties) so stdClass becomes array
            $existingValue = json_decode(
                json_encode($object->vars()->{$propertyData['key_name']}->getValue()),
                true
            );

            $datalistItemType = null;
            if (in_array($propertyData['value_type'], ['datalist-strict', 'datalist-non-strict'], true)) {
                $datalistItemType = $db->fetchOne(
                    $db->select()
                        ->from(['cdp' => 'director_property'], ['value_type' => 'cdp.value_type'])
                        ->where('cdp.parent_uuid = ?', DbUtil::quoteBinaryCompat($uuidBytes, $db))
                );
            }

            $isCompatibleType = match (true) {
                $propertyData['value_type'] === 'string' => is_string($existingValue),
                $propertyData['value_type'] === 'number' => is_numeric($existingValue),
                $propertyData['value_type'] === 'bool'   => is_bool($existingValue),
                in_array($propertyData['value_type'], ['datalist-strict', 'datalist-non-strict'], true)
                    => $datalistItemType === 'dynamic-array' ? is_array($existingValue) : is_string($existingValue),
                default => is_array($existingValue),
            };

            if ($isCompatibleType) {
                $propertyData['value'] = $existingValue;
            }
        }

        $inheritedVars = json_decode(json_encode($object->getInheritedVars()), true);
        if (isset($inheritedVars[$propertyData['key_name']])) {
            $propertyData['inherited'] = $inheritedVars[$propertyData['key_name']];
            $propertyData['inherited_from'] = $object->getOriginsVars()->{$propertyData['key_name']};
        }

        $newItem = $form->prepareNewPropertyRow($propertyData, $index);
        $newSlotIndex = $nextSlotIndex + 1;

        // Fill the slot with the new DictionaryItem + next empty slot
        $slotContent = new HtmlDocument();
        $slotContent->add($newItem);
        $slotContent->add(Html::tag('div', ['id' => 'new-var-slot-' . $newSlotIndex]));
        $this->addPart($slotContent, 'new-var-slot-' . $nextSlotIndex);

        if ($isTakeOver) {
            // The inherited row now lives in the own box, drop its old place
            $this->addPart(new HtmlDocument(), 'custom-var-row-' . $index);
        }

        // Update item-count input
        $itemCount = $form->createElement(
            'hidden',
            'properties[item-count]',
            ['value' => count($rows)]
        );

        $this->addPart(
            $itemCount,
            'properties-item-count'
        );

        // Update Add Custom Variable button with new slot index
        $buttonUrl = Url::fromPath(
            'director/' . $this->getType() . '/add-var',
            ['uuid' => Uuid::fromBytes($object->get('uuid'))->toString(), 'nextSlotIndex' => $newSlotIndex]
        );

        $buttonUrl->getParams()->addValues('addedVarUuids', $addedVarUuids);
        $buttonUrl->getParams()->addValues('requiredVarUuids', $requiredVarUuids);
        $buttonUrl->getParams()->addValues('removedVars', $removedVars);

        $this->addPart(
            (new ButtonLink(
                $this->translate('Add Custom Variable'),
                $buttonUrl->getAbsoluteUrl(),
                null,
                ['class' => 'control-button']
            ))->openInModal(),
            'add-custom-var-button'
        );

        // Update hidden addedVarUuids/requiredVarUuids inputs so POST form submission carries them
        $addedUuidsContainer = new HtmlDocument();
        $addedUuidsElement = $form->createElement(
            'hidden',
            'addedVarUuids',
            [
                'value' => implode(',', $addedVarUuids)
            ]
        );

        $addedUuidsContainer->addHtml($addedUuidsElement);
        $this->addPart($addedUuidsContainer, 'added-var-uuids');

        $requiredUuidsContainer = new HtmlDocument();
        $requiredUuidsElement = $form->createElement(
            'hidden',
            'requiredVarUuids',
            [
                'value' => implode(',', $requiredVarUuids)
            ]
        );

        $requiredUuidsContainer->addHtml($requiredUuidsElement);
        $this->addPart($requiredUuidsContainer, 'required-var-uuids');
    }

    /**
     * Prepare the Custom Properties Form for hosts, services, apply rules and service sets
     *
     * @param IcingaObject    $object
     * @param IcingaHost|null $host
     * @param string[]        $addedVarUuids    UUID strings of properties added this session
     * @param string[]        $requiredVarUuids UUID strings of properties marked required this session
     *
     * @return ?CustomVariablesForm
     */
    public function prepareCustomPropertiesForm(
        IcingaObject $object,
        ?IcingaHost $host = null,
        array $addedVarUuids = [],
        array $requiredVarUuids = []
    ): ?CustomVariablesForm {
        $isOverrideVars = $host !== null;
        if ($isOverrideVars) {
            $storedVars = $host->getOverriddenServiceVars($object);
        } else {
            $storedVars = $object->getVars();
            unset($storedVars->{'_override_servicevars'});
        }

        $vars = json_decode(json_encode($storedVars), true);
        $inheritedVars = json_decode(json_encode($object->getInheritedVars()), JSON_OBJECT_AS_ARRAY);
        $origins = $object->getOriginsVars();

        $objectProperties = $this->getObjectCustomProperties(
            $object,
            $isOverrideVars,
            $addedVarUuids,
            $requiredVarUuids
        );
        $form = (new CustomVariablesForm($object, $objectProperties))
            ->setGroups($this->getCustomPropertyGroups($object, $this->getCustomPropertySources($object)))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->setAddedVarUuids($addedVarUuids)
            ->setRequiredVarUuids($requiredVarUuids)
            ->setIsAdmin($this->hasPermission(Permission::ADMIN));
        if (empty($objectProperties)) {
            return $form;
        }

        $result = [];
        foreach ($objectProperties as $row) {
            if (array_key_exists($row['key_name'], $vars)) {
                $row['value'] = $vars[$row['key_name']];
            }

            if (isset($inheritedVars[$row['key_name']]) && ! $isOverrideVars) {
                $row['inherited'] = $inheritedVars[$row['key_name']];
                $row['inherited_from'] = $origins->{$row['key_name']};
            }

            $result[] = $row;
        }

        $form->load($result);

        return $form;
    }

    /**
     * Prepare the custom variables information header for service apply for rule
     *
     * @return void
     */
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

        $applyForHeader = new HtmlElement('div', Attributes::create(['class' => ['apply-for-header']]));
        $applyForHeaderContent = HtmlElement::create(
            'div',
            Attributes::create(['class' => ['apply-for-header-content']])
        );

        if ($fetchVar->value_type !== 'dynamic-dictionary') {
            $applyForHeaderContent->addHtml(
                Text::create(sprintf(
                    $this->translate(
                        'The values of selected host variable for apply-for-rule'
                        . ' is accessible through %s.'
                    ),
                    '$value$'
                ))
            );

            $applyForHeader->addHtml($applyForHeaderContent);

            $this->content()->addHtml($applyForHeader);

            return;
        }

        $applyForHeaderContent->addHtml(
            Text::create(sprintf(
                $this->translate(
                    'The values of selected host variable for apply-for-rule'
                    . ' are accessible through %s and keys through %s.'
                ),
                '$value$',
                '$key$'
            ))
        );

        $applyForHeader->addHtml($applyForHeaderContent);

        $this->content()->addHtml($applyForHeader);

        $dictionaryKeys = $this->fetchNestedDictionaryKeys(DbUtil::binaryResult($fetchVar->uuid));
        if (empty($dictionaryKeys)) {
            return;
        }

        $content = [];
        $configVariables = new HtmlElement('table', Attributes::create(['class' => 'key-value-table']));
        foreach ($dictionaryKeys as $keyAttributes) {
            if (preg_match('/[^a-zA-Z0-9_]/', $keyAttributes['key_name'])) {
                $config = '$value["' . $keyAttributes['key_name'] . '"]';
            } else {
                $config = '$value.' . $keyAttributes['key_name'];
            }

            $content = [$this->createKey(
                $keyAttributes['key_name'],
                $keyAttributes['label'] ?? $keyAttributes['key_name']
            )];

            if ($keyAttributes['value_type'] !== 'fixed-dictionary') {
                $content[] = $this->createValue($config . '$');

                $configVariables->addHtml(new HtmlElement(
                    'tr',
                    Attributes::create(['class' => 'key-value-item']),
                    ...$content
                ));

                continue;
            }

            $nestedContent = [];
            foreach ($this->fetchNestedDictionaryKeys($keyAttributes['uuid']) as $nestedKeyAttributes) {
                if (preg_match('/[^a-zA-Z0-9_]/', $nestedKeyAttributes['key_name'])) {
                    $nestedConfig = $config . '["' . $nestedKeyAttributes['key_name'] . '"]$';
                } else {
                    $nestedConfig = $config . '.' . $nestedKeyAttributes['key_name'] . '$';
                }

                $nestedKeyName = $nestedKeyAttributes['key_name'];
                $nestedLabel = $nestedKeyAttributes['label'] ?? $nestedKeyAttributes['key_name'];
                $nestedContent[] = $this->createKey($nestedKeyName, $nestedLabel);
                $nestedContent[] = $this->createValue($nestedConfig);
            }

            if (preg_match('/[^a-zA-Z0-9_]/', $keyAttributes['key_name'])) {
                $value = '$value["' . $keyAttributes['key_name'] . '"]$';
            } else {
                $value = '$value.' . $keyAttributes['key_name'] . '$';
            }

            $content[] = new HtmlElement(
                'td',
                Attributes::create(['class' => 'value']),
                new HtmlElement(
                    'div',
                    null,
                    new HtmlElement(
                        'div',
                        null,
                        Text::create($value)
                    ),
                    new HtmlElement(
                        'table',
                        Attributes::create(['class' => 'key-value-table']),
                        new HtmlElement(
                            'tr',
                            Attributes::create(
                                ['class' => 'key-value-item']
                            ),
                            ...$nestedContent
                        )
                    )
                )
            );

            $configVariables->addHtml(
                new HtmlElement(
                    'tr',
                    Attributes::create(['class' => 'key-value-item']),
                    ...$content
                )
            );
        }

        if (empty($content)) {
            return;
        }

        $this->content()->addHtml(new HtmlElement(
            'div',
            Attributes::create(['class' => ['apply-for-header']]),
            HtmlElement::create(
                'div',
                Attributes::create(['class' => ['apply-for-header-content']]),
                [
                    Text::create($this->translate(
                        'The value of each nested key of the selected host dictionary variable for '
                        . 'apply-for-rule is accessible through $value$, as shown in the table below:'
                    )),
                    $configVariables
                ]
            )
        ));
    }

    /**
     * Fetch the child properties nested directly under the given dictionary
     *
     * @param string $dictionaryUuid
     *
     * @return array
     */
    private function fetchNestedDictionaryKeys(string $dictionaryUuid): array
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
            )->where("parent_uuid = ?", DbUtil::quoteBinaryCompat($dictionaryUuid, $db->getDbAdapter()));

        return $db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC);
    }

    /**
     * Fetch custom variable information for the given variable name
     *
     * @param string $varName
     *
     * @return mixed
     */
    protected function fetchVar(string $varName)
    {
        $dbAdapter = $this->object->getConnection()->getDbAdapter();
        $query = $dbAdapter->select()
            ->from(
                ['dp' => 'director_property'],
                ['*']
            )
            ->where('parent_uuid IS NULL')
            ->where('key_name = ?', $varName);

        return $dbAdapter->fetchRow($query);
    }

    /**
     * Get custom properties for the object, including session-added ones
     *
     * Rows come in the order of their boxes, see {@see getCustomPropertyGroups()},
     * and name their box in `group`. A property added this session that the
     * object reaches through a template or its check command replaces that row
     * at the same position, so it keeps its index in the form, and moves to the
     * object's own box. Other added properties come last.
     *
     * @param IcingaObject $object
     * @param bool         $isOverrideVars   Whether to fetch service override vars instead of
     *                                       the object's own
     * @param string[]     $addedVarUuids    UUID strings of properties added this session
     * @param string[]     $requiredVarUuids UUID strings of properties marked required this session
     *
     * @return array<string, array<string, mixed>> Rows keyed by property name
     */
    protected function getObjectCustomProperties(
        IcingaObject $object,
        bool $isOverrideVars = false,
        array $addedVarUuids = [],
        array $requiredVarUuids = []
    ): array {
        if ($object->uuid === null) {
            return [];
        }

        $db = $this->db();
        $sources = $this->getCustomPropertySources($object);
        $groupPositions = array_flip(array_column($this->getCustomPropertyGroups($object, $sources), 'key'));

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

        $result = [];
        $origins = (array) $object->getOriginsVars();
        foreach (CustomPropertyPlacement::place($sources, $origins) as $name => $property) {
            $row = [
                'key_name'      => $property['key_name'],
                'uuid'          => $property['uuid'],
                'value_type'    => $property['value_type'],
                'label'         => $property['label'],
                'allow_removal' => $property['allow_removal'],
                'required'      => $property['required'],
                'group'         => $this->getCustomPropertyGroupKey($object, $property['source']),
            ];

            if (isset($vars[$name])) {
                $row['value'] = $vars[$name];
            }

            $result[$name] = $row;
        }

        $this->sortCustomProperties($result, $groupPositions, $db);

        if (! empty($addedVarUuids)) {
            $uuidBytes = array_map(
                fn($uuid) => Uuid::fromString($uuid)->getBytes(),
                $addedVarUuids
            );

            $addedQuery = $db->getDbAdapter()
                ->select()
                ->from(
                    ['dp' => 'director_property'],
                    [
                        'key_name'   => 'dp.key_name',
                        'uuid'       => 'dp.uuid',
                        'value_type' => 'dp.value_type',
                        'label'      => 'dp.label'
                    ]
                )
                ->where('dp.uuid IN (?)', DbUtil::quoteBinaryCompat($uuidBytes, $db->getDbAdapter()))
                ->where('dp.parent_uuid IS NULL');

            $addedRows = $db->getDbAdapter()->fetchAll($addedQuery, fetchMode: PDO::FETCH_ASSOC);
            foreach ($addedRows as &$row) {
                $row['uuid'] = DbUtil::binaryResult($row['uuid']);
            }

            unset($row);

            $uuidBytes = array_flip($uuidBytes);
            usort($addedRows, function ($a, $b) use ($uuidBytes) {
                $posA = $uuidBytes[$a['uuid']] ?? PHP_INT_MAX;
                $posB = $uuidBytes[$b['uuid']] ?? PHP_INT_MAX;

                return $posA <=> $posB;
            });

            foreach ($addedRows as $row) {
                if (($result[$row['key_name']]['group'] ?? null) === 'own') {
                    // attached here already, nothing to take over
                    continue;
                }

                $row['allow_removal'] = true;
                $row['new'] = true;
                $row['group'] = 'own';
                $row['required'] = in_array(Uuid::fromBytes($row['uuid'])->toString(), $requiredVarUuids, true);

                if (isset($vars[$row['key_name']])) {
                    $row['value'] = $vars[$row['key_name']];
                }

                // an inherited row keeps its position, so its index in the form stays the same
                $result[$row['key_name']] = $row;
            }
        }

        return $result;
    }

    /**
     * Get the boxes of the Custom Variables tab, in display order
     *
     * The object's own box comes first and always shows on templates and
     * commands, which can add variables. One box per template follows, in
     * import order, and on a command one box per imported command. The box of
     * a host's or service's check command comes last, under the name of the
     * check command, and starts folded.
     *
     * @param IcingaObject           $object
     * @param CustomPropertySource[] $sources The sources of the object
     *
     * @return array<int, array{key: string, label: string, open: bool, always: bool}>
     */
    protected function getCustomPropertyGroups(IcingaObject $object, array $sources): array
    {
        $type = $object->getShortTableName();
        $name = $object->getObjectName();
        if ($type === 'command') {
            $ownLabel = sprintf($this->translate('Added in this command "%s"'), $name);
        } elseif ($object->isTemplate()) {
            $ownLabel = sprintf($this->translate('Added in this template "%s"'), $name);
        } else {
            $ownLabel = sprintf($this->translate('Added in this object "%s"'), $name);
        }

        $groups = [[
            'key'    => 'own',
            'label'  => $ownLabel,
            'open'   => true,
            'always' => $object->definesCustomProperties(),
        ]];

        $checkCommand = null;
        foreach (array_slice($sources, 1) as $source) {
            if ($source->type !== $type) {
                $checkCommand ??= $source;

                continue;
            }

            $label = $source->kind === CustomPropertySourceKind::Command
                ? $this->translate('Added by command "%s"')
                : $this->translate('Added by template "%s"');
            $groups[] = [
                'key'    => $source->getKey(),
                'label'  => sprintf($label, $source->name),
                'open'   => true,
                'always' => false,
            ];
        }

        if ($checkCommand !== null) {
            $groups[] = [
                'key'    => 'command',
                'label'  => sprintf($this->translate('Added by command "%s"'), $checkCommand->name),
                'open'   => false,
                'always' => false,
            ];
        }

        return $groups;
    }

    /**
     * Get the sources of the given object, loaded once per request
     *
     * @param IcingaObject $object
     *
     * @return CustomPropertySource[]
     */
    protected function getCustomPropertySources(IcingaObject $object): array
    {
        $id = spl_object_id($object);
        if (! isset($this->customPropertySources[$id])) {
            $this->customPropertySources[$id] = (new CustomPropertySources($this->db()))->getSources($object);
        }

        return $this->customPropertySources[$id];
    }

    /**
     * Get the key of the box that shows the properties of the given source
     *
     * @param IcingaObject         $object
     * @param CustomPropertySource $source
     *
     * @return string
     */
    protected function getCustomPropertyGroupKey(IcingaObject $object, CustomPropertySource $source): string
    {
        if ($source->kind === CustomPropertySourceKind::Own) {
            return 'own';
        }

        if ($source->type !== $object->getShortTableName()) {
            return 'command';
        }

        return $source->getKey();
    }

    /**
     * Sort property rows by box, then by value type, nesting and name
     *
     * @param array<string, array<string, mixed>> $rows           Rows keyed by property name
     * @param array<string, int>                  $groupPositions Position of each box by key
     * @param DbConnection                        $db
     *
     * @return void
     */
    protected function sortCustomProperties(array &$rows, array $groupPositions, DbConnection $db): void
    {
        if (empty($rows)) {
            return;
        }

        $dba = $db->getDbAdapter();
        $children = [];
        $query = $dba->select()
            ->from('director_property', ['parent_uuid' => 'parent_uuid', 'cnt' => 'COUNT(*)'])
            ->where('parent_uuid IN (?)', DbUtil::quoteBinaryCompat(array_column($rows, 'uuid'), $dba))
            ->group('parent_uuid');
        foreach ($dba->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $children[DbUtil::binaryResult($row['parent_uuid'])] = (int) $row['cnt'];
        }

        $typeOrder = array_flip([
            'string',
            'number',
            'bool',
            'datalist-strict',
            'datalist-non-strict',
            'dynamic-array',
            'fixed-dictionary',
            'dynamic-dictionary',
        ]);

        uasort($rows, fn($a, $b) => [
            $groupPositions[$a['group']] ?? PHP_INT_MAX,
            $typeOrder[$a['value_type']] ?? PHP_INT_MAX,
            $children[$a['uuid']] ?? 0,
            $a['key_name'],
        ] <=> [
            $groupPositions[$b['group']] ?? PHP_INT_MAX,
            $typeOrder[$b['value_type']] ?? PHP_INT_MAX,
            $children[$b['uuid']] ?? 0,
            $b['key_name'],
        ]);
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

    private function createKey(mixed $keyName, mixed $label): HtmlElement
    {
        return new HtmlElement(
            'td',
            Attributes::create(['class' => 'key']),
            new HtmlElement(
                'div',
                null,
                Text::create($label . ' (' . $keyName . ')')
            )
        );
    }

    private function createValue(string $value): HtmlElement
    {
        return new HtmlElement(
            'td',
            Attributes::create(['class' => 'value']),
            new HtmlElement(
                'div',
                null,
                Text::create($value)
            )
        );
    }
}
