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
use Icinga\Module\Director\CustomVariable\ObjectCustomPropertySources;
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
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
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
use ipl\Html\BaseHtmlElement;
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

/**
 * @phpstan-import-type Section from ObjectCustomPropertySources
 */
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
     * Carries the added and required var uuids along on the redirect,
     * so the variables tab still knows about them after the reload
     */
    public function addVarAction(): void
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();
        $this->assertDefinesCustomProperties($object);
        $this->view->title = sprintf($this->translate('Add Custom Variable: %s'), $this->object->getObjectName());

        $addedVarUuids = $this->params->getValues('addedVarUuids');
        $requiredVarUuids = $this->params->getValues('requiredVarUuids');
        $removedKeys = $this->params->getValues('removedKeys');
        $nextSlotIndex = (int) $this->params->shift('nextSlotIndex');

        $form = (new ObjectCustomvarForm($this->db(), $object, $addedVarUuids))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(ObjectCustomvarForm::ON_SUBMIT, function (ObjectCustomvarForm $form) use (
                $object,
                $addedVarUuids,
                $requiredVarUuids,
                $removedKeys,
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
                $redirectUrl->getParams()->addValues('removedKeys', $removedKeys);

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

        $addedVarUuids = array_unique(array_merge(
            $this->params->getValues('addedVarUuids'),
            array_filter(explode(',', $this->getRequest()->getPost('addedVarUuids', '')))
        ));

        $requiredVarUuids = array_unique(array_merge(
            $this->params->getValues('requiredVarUuids'),
            array_filter(explode(',', $this->getRequest()->getPost('requiredVarUuids', '')))
        ));

        $removedKeys = $this->params->getValues('removedKeys');

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
                        ['addedVarUuids', 'requiredVarUuids', 'removedKeys', 'newVarUuid', 'nextSlotIndex']
                    ));
                }
            )->on(
                CustomVariablesForm::ON_REQUEST,
                function (
                    ServerRequestInterface $request,
                    CustomVariablesForm $form
                ) use (
                    $newVarUuid,
                    $nextSlotIndex,
                    $removedKeys
                ) {
                    if ($newVarUuid === null) {
                        return;
                    }

                    $this->sendNewVarMultipartUpdate($form, $newVarUuid, $nextSlotIndex, $removedKeys);
                    $this->params->remove('addedVarUuids');
                    $this->params->remove('requiredVarUuids');
                    $this->params->remove('removedKeys');
                    $this->getResponse()->setHeader('X-Icinga-Location-Query', $this->params->toString());
                }
            )->handleRequest($this->getServerRequest());

        if ($newVarUuid !== null) {
            return;
        }

        $this->prepareApplyForHeader();

        if ($object->definesCustomProperties() && $this->hasPermission(Permission::ADMIN)) {
            $slotIndex = $form->getElement('properties')->getItemCount();

            $buttonUrl = Url::fromPath(
                'director/' . $this->getType() . '/add-var',
                ['uuid' => $this->getUuidFromUrl(), 'nextSlotIndex' => $slotIndex]
            );
            $buttonUrl->getParams()->addValues('addedVarUuids', $addedVarUuids);
            $buttonUrl->getParams()->addValues('requiredVarUuids', $requiredVarUuids);
            // Rows removed but not saved yet are gone from the screen, so the rows below moved up
            $buttonUrl->getParams()->addValues('removedKeys', $form->getElement('properties')->getItemsToRemove());

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
     * @param CustomVariablesForm $form
     * @param string              $newVarUuid
     * @param int                 $nextSlotIndex
     * @param string[]            $removedKeys
     *
     * @return void
     */
    private function sendNewVarMultipartUpdate(
        CustomVariablesForm $form,
        string $newVarUuid,
        int $nextSlotIndex,
        array $removedKeys = []
    ): void {
        if (! $this->hasPermission(Permission::ADMIN)) {
            return;
        }

        $object = $form->getObject();
        $addedVarUuids = $form->getAddedVarUuids();
        $requiredVarUuids = $form->getRequiredVarUuids();
        $type = $object->getShortTableName();
        $db = $this->db()->getDbAdapter();
        $uuidBytes = Uuid::fromString($newVarUuid)->getBytes();

        $query = $db->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'key_name'   => 'dp.key_name',
                    'uuid'       => 'dp.uuid',
                    'value_type' => 'dp.value_type',
                    'label'      => 'dp.label'
                ]
            )
            ->where('dp.uuid = ?', DbUtil::quoteBinaryCompat($uuidBytes, $db));

        $row = $db->fetchRow($query, fetchMode: PDO::FETCH_ASSOC);
        if (! $row) {
            return;
        }

        $propertyData = [
            'key_name'       => $row['key_name'],
            'uuid'           => DbUtil::binaryResult($row['uuid']),
            'value_type'     => $row['value_type'],
            'label'          => $row['label'],
            'allow_removal'  => true,
            'new'            => true,
            'required'       => in_array($newVarUuid, $requiredVarUuids, true),
            $type . '_uuid'  => $object->get('uuid')
        ];

        if (isset($object->vars()->{$row['key_name']})) {
            // Normalize via JSON round-trip (same as getObjectCustomProperties) so stdClass becomes array
            $existingValue = json_decode(
                json_encode($object->vars()->{$row['key_name']}->getValue()),
                true
            );

            $datalistItemType = null;
            if (in_array($row['value_type'], ['datalist-strict', 'datalist-non-strict'], true)) {
                $datalistItemType = $db->fetchOne(
                    $db->select()
                        ->from(['cdp' => 'director_property'], ['value_type' => 'cdp.value_type'])
                        ->where('cdp.parent_uuid = ?', DbUtil::quoteBinaryCompat($row['uuid'], $db))
                );
            }

            $isCompatibleType = match (true) {
                $row['value_type'] === 'string' => is_string($existingValue),
                $row['value_type'] === 'number' => is_numeric($existingValue),
                $row['value_type'] === 'bool' => is_bool($existingValue),
                in_array($row['value_type'], ['datalist-strict', 'datalist-non-strict'], true)
                    => $datalistItemType === 'dynamic-array' ? is_array($existingValue) : is_string($existingValue),
                default => is_array($existingValue),
            };

            if ($isCompatibleType) {
                $propertyData['value'] = $existingValue;
            }
        }

        $index = $this->getSwappedRowIndex($form, $row['key_name'], $nextSlotIndex, $removedKeys);
        if ($index !== null) {
            // Keep the "Inherited from" hint the old row had
            $inherited = json_decode(json_encode($object->getInheritedVars()), true);
            if (isset($inherited[$row['key_name']])) {
                $propertyData['inherited'] = $inherited[$row['key_name']];
                $propertyData['inherited_from'] = $object->getOriginsVars()->{$row['key_name']};
            }
        }

        // A swapped row keeps the number of the row it replaces. Save matches rows by number.
        $newItem = $form->prepareNewPropertyRow($propertyData, $index ?? $nextSlotIndex);
        if ($index === null) {
            $newSlotIndex = $nextSlotIndex + 1;
            $this->appendNewRow($form, $newItem, $nextSlotIndex);
        } else {
            $newSlotIndex = $nextSlotIndex;
            $this->swapInheritedRow($newItem, $row['key_name']);
        }

        // Update Add Custom Variable button with new slot index
        $buttonUrl = Url::fromPath(
            'director/' . $this->getType() . '/add-var',
            ['uuid' => Uuid::fromBytes($object->get('uuid'))->toString(), 'nextSlotIndex' => $newSlotIndex]
        );

        $buttonUrl->getParams()->addValues('addedVarUuids', $addedVarUuids);
        $buttonUrl->getParams()->addValues('requiredVarUuids', $requiredVarUuids);
        $buttonUrl->getParams()->addValues('removedKeys', $removedKeys);

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
     * Fill the free slot with the new row and add an empty slot behind it
     *
     * @param CustomVariablesForm $form
     * @param BaseHtmlElement $newItem
     * @param int $slotIndex The number of the free slot
     *
     * @return void
     */
    private function appendNewRow(CustomVariablesForm $form, BaseHtmlElement $newItem, int $slotIndex): void
    {
        $slotContent = new HtmlDocument();
        $slotContent->add($newItem);
        $slotContent->add(Html::tag('div', ['id' => 'new-var-slot-' . ($slotIndex + 1)]));
        $this->addPart($slotContent, 'new-var-slot-' . $slotIndex);

        // One more row, so the count goes up
        $this->addPart(
            $form->createElement('hidden', 'properties[item-count]', ['value' => $slotIndex + 1]),
            'properties-item-count'
        );
    }

    /**
     * Move the new row into the own box and clear the inherited row it replaces
     *
     * @param BaseHtmlElement $newItem
     * @param string $key
     *
     * @return void
     */
    private function swapInheritedRow(BaseHtmlElement $newItem, string $key): void
    {
        $this->addPart($newItem, Dictionary::getSwapTargetId($key));
        $this->addPart(new HtmlDocument(), Dictionary::getInheritedRowId($key));
    }

    /**
     * Get the row number of the inherited row a variable is about to replace
     *
     * @param CustomVariablesForm $form
     * @param string $key
     * @param int $nextSlotIndex The number the next new row gets
     * @param string[] $removedKeys Variables removed on screen but not saved yet
     *
     * @return ?int
     */
    private function getSwappedRowIndex(
        CustomVariablesForm $form,
        string $key,
        int $nextSlotIndex,
        array $removedKeys
    ): ?int {
        $index = $form->getPropertyIndex($key, $removedKeys);

        // A brand new variable comes last, so only a number below the next free one was on screen before
        return $index !== null && $index < $nextSlotIndex ? $index : null;
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
            $requiredVarUuids,
            $host
        );
        $form = (new CustomVariablesForm($object, $objectProperties))
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
     * Custom variable form submit handler
     *
     * @param CustomVariablesForm $form
     * @param IcingaObject $host
     *
     * @return void
     */
    protected function customVarFormOnSubmit(CustomVariablesForm $form, IcingaObject $host): void
    {
        $form
            ->on(
                CustomVariablesForm::ON_SUBMIT,
                function (CustomVariablesForm $form) use ($host) {
                    if ($form->varsHasBeenModified()) {
                        Notification::success(
                            sprintf(
                                $this->translate(
                                    "Custom variables have been successfully overridden"
                                    . " for service '%s' on host '%s'"
                                ),
                                $form->object->getObjectName(),
                                $host->getObjectName()
                            )
                        );
                    } else {
                        Notification::success(
                            $this->translate('There is nothing to change.')
                        );
                    }

                    $this->redirectNow(Url::fromRequest());
                }
            );
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
     * Get the expression for ordering the custom properties by value type.
     *
     * @param DbConnection $db
     * @param array        $types
     *
     * @return string
     */
    private function valueTypeOrderExpr(DbConnection $db, array $types): string
    {
        if ($db->isPgsql()) {
            $cases = [];
            foreach ($types as $i => $type) {
                $cases[] = "WHEN '$type' THEN " . ($i + 1);
            }
            return 'CASE dp.value_type ' . implode(' ', $cases) . ' ELSE ' . (count($types) + 1) . ' END';
        }

        return "FIELD(dp.value_type, '" . implode("', '", $types) . "')";
    }

    /**
     * Get custom properties for the object, including session-added ones.
     *
     * @param IcingaObject $object
     * @param bool         $isOverrideVars   Whether to fetch service override vars instead of
     *                                       the object's own
     * @param string[]     $addedVarUuids    UUID strings of properties added this session
     * @param string[]     $requiredVarUuids UUID strings of properties marked required this session
     * @param ?IcingaHost  $host             Host that holds the overrides, defaults to the controller's object
     *
     * @return array
     */
    protected function getObjectCustomProperties(
        IcingaObject $object,
        bool $isOverrideVars = false,
        array $addedVarUuids = [],
        array $requiredVarUuids = [],
        ?IcingaHost $host = null
    ): array {
        if ($object->uuid === null) {
            return [];
        }

        $db = $this->db();
        $objectUuid = $object->get('uuid');

        // The list is in display order, so a position also tells where a section is shown
        $uuidsByType = [];
        $sourceByUuid = [];
        foreach (ObjectCustomPropertySources::for($object, $db) as $position => $source) {
            $uuidsByType[$source['type']][] = DbUtil::quoteBinaryCompat($source['uuid'], $db->getDbAdapter());
            $sourceByUuid[$source['uuid']] = $source + ['order' => $position];
        }

        // Hosts and commands have separate ids, so each type is queried on its own tables
        $properties = [];
        $attachments = [];
        foreach ($uuidsByType as $sourceType => $uuids) {
            foreach ($this->fetchReachableProperties($sourceType, $uuids, $db) as $row) {
                $properties[$row['uuid']] ??= $row;
            }

            foreach ($this->fetchReachableAttachments($sourceType, $uuids, $db) as $keyName => $rows) {
                $attachments[$keyName] = array_merge($attachments[$keyName] ?? [], $rows);
            }
        }

        if ($isOverrideVars) {
            $host ??= $this->object;
            $vars = json_decode(json_encode($host->getOverriddenServiceVars($object->getObjectName())), true);
        } else {
            $vars = json_decode(json_encode($object->getVars()), true);
        }

        $origins = $isOverrideVars ? null : $object->getOriginsVars();

        $result = [];
        foreach ($properties as $row) {
            $originName = $origins->{$row['key_name']} ?? null;
            $row = $this->resolveSection(
                $row,
                $attachments[$row['key_name']] ?? [],
                $sourceByUuid,
                $objectUuid,
                is_string($originName) ? $originName : null
            );

            if (isset($vars[$row['key_name']])) {
                $row['value'] = $vars[$row['key_name']];
            }

            $result[$row['key_name']] = $row;
        }

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
                $row['allow_removal'] = true;
                $row['section'] = ['kind' => ObjectCustomPropertySources::OWN, 'name' => '', 'order' => -1];

                // Only a variable the object itself attaches is old. One that is just inherited
                // still needs its own attachment when it is saved.
                if (! ($result[$row['key_name']]['allow_removal'] ?? false)) {
                    $row['new'] = true;
                }

                $row['required'] = in_array(Uuid::fromBytes($row['uuid'])->toString(), $requiredVarUuids, true);

                if (isset($vars[$row['key_name']])) {
                    $row['value'] = $vars[$row['key_name']];
                }

                $result[$row['key_name']] = $row;
            }
        }

        return $result;
    }

    /**
     * Get the property definitions reachable through the given object, template or command uuids
     *
     * One row per property, even if it's attached more than once. Who
     * attached it and whether it's required is worked out separately.
     *
     * @param string $type
     * @param string[] $uuids
     * @param DbConnection $db
     *
     * @return array
     */
    private function fetchReachableProperties(string $type, array $uuids, DbConnection $db): array
    {
        $query = $db->getDbAdapter()
            ->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'key_name'   => 'dp.key_name',
                    'uuid'       => 'dp.uuid',
                    'value_type' => 'dp.value_type',
                    'label'      => 'dp.label',
                    'children'   => 'COUNT(cdp.uuid)'
                ]
            )
            ->join(
                ['iop' => "icinga_$type" . '_property'],
                'dp.uuid = iop.property_uuid',
                []
            )
            ->joinLeft(
                ['cdp' => 'director_property'],
                'cdp.parent_uuid = dp.uuid',
                []
            )
            ->where('iop.' . $type . '_uuid IN (?)', $uuids)
            ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
            ->order($this->valueTypeOrderExpr($db, [
                'string',
                'number',
                'bool',
                'datalist-strict',
                'datalist-non-strict',
                'dynamic-array',
                'fixed-dictionary',
                'dynamic-dictionary'
            ]))
            ->order('children')
            ->order('key_name');

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $row['uuid'] = DbUtil::binaryResult($row['uuid']);
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Get every attachment row behind the given source uuids, grouped by key_name
     *
     * A property attached by two or more sources, such as templates, the
     * object itself or the command, ends up with more than one row here.
     * What that means is decided elsewhere, not by just picking one.
     *
     * @param string $type
     * @param string[] $uuids
     * @param DbConnection $db
     *
     * @return array<string, array<int, array{uuid: string, required: bool}>>
     */
    private function fetchReachableAttachments(string $type, array $uuids, DbConnection $db): array
    {
        $query = $db->getDbAdapter()
            ->select()
            ->from(['dp' => 'director_property'], ['key_name' => 'dp.key_name'])
            ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [
                $type . '_uuid' => 'iop.' . $type . '_uuid',
                'required'      => 'iop.required'
            ])
            ->where('iop.' . $type . '_uuid IN (?)', $uuids);

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']][] = [
                'uuid'     => DbUtil::binaryResult($row[$type . '_uuid']),
                'required' => $row['required'] === 'y'
            ];
        }

        return $result;
    }

    /**
     * Work out which section a property is shown in
     *
     * The object and its templates decide first. The command only gets the row
     * when none of them attaches the property. It is shown under the command
     * the object uses, also when only a template of that command attaches it.
     *
     * @param array<string, mixed> $property
     * @param array<int, array{uuid: string, required: bool}> $attachments
     * @param array<string, Section> $sourceByUuid
     * @param string $objectUuid
     * @param ?string $originName The template that gives the current value, if there is one
     *
     * @return array<string, mixed>
     */
    private function resolveSection(
        array $property,
        array $attachments,
        array $sourceByUuid,
        string $objectUuid,
        ?string $originName
    ): array {
        $viaObject = [];
        $candidates = [];
        foreach ($attachments as $attachment) {
            $source = $sourceByUuid[$attachment['uuid']];
            if ($source['kind'] !== ObjectCustomPropertySources::COMMAND) {
                $viaObject[] = $attachment;
                $candidates[] = $source;
            }
        }

        if (empty($candidates)) {
            // Only the command or one of its templates has it, so it can be neither removed nor required from here
            $property['allow_removal'] = false;
            $property['required'] = false;
            $property['section'] = ObjectCustomPropertySources::pickCommandSource($sourceByUuid);

            return $property;
        }

        // The attachments come back in any order, but the import order decides which template wins
        usort($candidates, fn(array $a, array $b) => $a['order'] <=> $b['order']);

        // Required is worked out over every attachment of the object and its templates,
        // also the ones that are not shown, so hiding a copy never makes a variable optional
        $property = $this->resolveEffectiveAttachment($property, $viaObject, $objectUuid);
        $property['section'] = ObjectCustomPropertySources::pickSource($candidates, $originName);

        return $property;
    }

    /**
     * Work out whether a property is required and removable for this object
     *
     * A direct attachment on the object always wins. Without one, any
     * ancestor requiring it is enough, an object can't quietly drop a
     * requirement it inherited.
     *
     * @param array $property
     * @param array<int, array{uuid: string, required: bool}> $attachments
     * @param string $objectUuid
     *
     * @return array
     */
    private function resolveEffectiveAttachment(array $property, array $attachments, string $objectUuid): array
    {
        $direct = null;
        $requiredByAnAncestor = false;

        foreach ($attachments as $attachment) {
            if ($attachment['uuid'] === $objectUuid) {
                $direct = $attachment;
            } elseif ($attachment['required']) {
                $requiredByAnAncestor = true;
            }
        }

        $property['allow_removal'] = $direct !== null;
        $property['required'] = $direct !== null ? $direct['required'] : $requiredByAnAncestor;

        return $property;
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
