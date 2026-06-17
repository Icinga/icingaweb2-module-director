<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\DeleteCustomVariableForm;
use Icinga\Module\Director\Forms\CustomVariableForm;
use Icinga\Module\Director\Web\Table\CustomvarVariantsTable;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Module\Director\Web\Widget\CustomVarFieldsTable;
use Icinga\Web\Notification;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use ipl\Web\Widget\EmptyStateBar;
use ipl\Web\Widget\ListItem;
use ipl\Web\Widget\Tabs;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;
use Zend_Db_Expr;

class CustomvarController extends CompatController
{
    /** @var Db */
    protected $db;

    /** @var ?UuidInterface */
    private ?UuidInterface $uuid = null;

    /** @var ?UuidInterface */
    private ?UuidInterface $parentUuid = null;

    public function init(): void
    {
        parent::init();

        $uuid = $this->params->shift('uuid');
        if ($uuid !== null) {
            $this->uuid = Uuid::fromString($uuid);
            $parentUuid = $this->params->shift('parent_uuid');

            if ($parentUuid) {
                $this->parentUuid = Uuid::fromString($parentUuid);
            }
        }

        $this->db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );
    }

    public function indexAction(): void
    {
        $uuid = $this->uuid;
        $parentUuid = $this->parentUuid ?? null;
        $parent = [];
        $db = $this->db->getDbAdapter();
        $property = $this->fetchProperty($uuid);
        if (empty($property)) {
            $this->redirectNow(Url::fromPath('director/variables'));
        }

        if ($parentUuid) {
            $parentUuid = Uuid::fromString($parentUuid);
            $parent = $this->fetchProperty($parentUuid);

            if ($parent['parent_uuid'] !== null) {
                $usedCount = $this->fetchPropertyUsedCount(Uuid::fromBytes($parent['parent_uuid']));
            } else {
                $usedCount = $this->fetchPropertyUsedCount($parentUuid);
            }
        } else {
            $usedCount = $this->fetchPropertyUsedCount($uuid);
        }

        $property['used_count'] = $usedCount;

        if ($property['value_type'] === 'dynamic-array' || str_starts_with($property['value_type'], 'datalist-')) {
            $itemTypeQuery = $db
                ->select()->from('director_property', 'value_type')
                ->where(
                    'parent_uuid = ? AND key_name = \'0\'',
                    Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db)
                );

            $property['item_type'] = $db->fetchOne($itemTypeQuery);
        }

        if (str_starts_with($property['value_type'], 'datalist-')) {
            $datalistId = $db
                ->select()->from(['dl' => 'director_datalist'], 'id')
                ->join(['dpl' => 'director_property_datalist'], 'dpl.list_uuid = dl.uuid', [])
                ->where(
                    'dpl.property_uuid = ?',
                    Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db)
                );

            $property['list'] = $db->fetchOne($datalistId);
        }

        $showFields = $this->showFields($property['value_type']);
        $propertyForm = (new CustomVariableForm($this->db, $uuid, $parentUuid !== null, $parentUuid))
            ->populate($property)
            ->setStoredKeyName($property['key_name'])
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(CustomVariableForm::ON_SENT, function (CustomVariableForm $form) use ($property, &$showFields) {
                $showFields = $showFields && $form->getValue('value_type') === $property['value_type'];
            })
            ->on(CustomVariableForm::ON_SUBMIT, function (CustomVariableForm $form) use ($usedCount) {
                if (
                    $usedCount > 0
                    && $form->getPopulatedValue('confirm_rename_change') !== 'y'
                ) {
                    $keyName = $form->getStoredKeyName();
                } else {
                    $keyName = $form->getValue('key_name');
                }

                Notification::success(sprintf(
                    $this->translate('Custom variable configuration "%s" has successfully been saved'),
                    $keyName
                ));

                $this->sendExtraUpdates(['#col1']);
                $redirectUrl = Url::fromPath(
                    'director/customvar',
                    ['uuid' => $form->getUUid()->toString()]
                );

                if ($form->getParentUUid()) {
                    $redirectUrl->addParams(['parent_uuid' => $form->getParentUUid()->toString()]);
                }

                $this->redirectNow($redirectUrl);
            });

        if ($parent) {
            $propertyForm
                ->setHideKeyNameElement($parent['value_type'] === 'fixed-array')
                ->setIsNestedField($parent['parent_uuid'] !== null);
        }

        $propertyForm->handleRequest($this->getServerRequest());
        $this->addContent($propertyForm);

        if ($showFields) {
            $this->addContent(new HtmlElement('h2', null, Text::create($this->translate('Fields'))));
            $button = (new ButtonLink(
                Text::create($this->translate('Create Field')),
                Url::fromPath('director/customvar/add-field', [
                    'uuid' => $uuid->toString()
                ]),
                null,
                ['class' => 'control-button']
            ))->openInModal();

            $fieldQuery = $db
                ->select()
                ->from(['dp' => 'director_property'], [])
                ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.parent_uuid', [])
                ->columns([
                    'uuid',
                    'parent_uuid',
                    'key_name',
                    'category_id',
                    'value_type',
                    'label',
                    'description',
                    'used_count' => $property['used_count'] > 0 ? 'COUNT(1)' : '0',
                ])
                ->where('parent_uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db))
                ->group('dp.uuid')
                ->order('key_name');

            $this->addContent($button);

            $fields = $db->fetchAll($fieldQuery);

            if (empty($fields)) {
                $this->addContent(
                    new EmptyStateBar(
                        $this->translate('No fields have been added yet')
                    )
                );
            } else {
                $this->addContent(new CustomVarFieldsTable($fields, true));
            }
        }

        if ($parentUuid) {
            $keyName = $parent['value_type'] === 'fixed-array'
                ? $property['label']
                : $property['key_name'];

            $title = $this->translate('Edit Field') . ': ' . $keyName;
        } else {
            $title = $this->translate('Custom Variable') . ': ' . $property['key_name'];
        }

        $this->setTitle($title);
        $this->setTitleTab('customvar');
    }

    public function usageAction(): void
    {
        $objectClass = null;
        $usageList = (new CustomVarObjectList($this->fetchCustomVarUsage()))
            ->setDetailActionsDisabled(false)
            ->on(
                CustomVarObjectList::BEFORE_ITEM_ADD,
                function (ListItem $item, $data) use (&$objectClass, &$usageList) {
                    if ($objectClass !== $data->object_class) {
                        $usageList->addHtml(
                            HtmlElement::create(
                                'li',
                                ['class' => 'list-item'],
                                HtmlElement::create('h2', content: ucfirst($data->object_class) . 's')
                            )
                        );
                        $objectClass = $data->object_class;
                    }
                }
            );

        $this->addContent($usageList);

        $this->setTitle($this->translate('Custom Variable Usage'));
        $this->setTitleTab('usage');
    }

    /**
     * Fetch the give custom variable usage in templates
     *
     * @return array
     */
    private function fetchCustomVarUsage(): array
    {
        $uuid = $this->uuid;
        $property = $this->fetchProperty($uuid);
        $db = $this->db->getDbAdapter();
        if (isset($property['parent_uuid'])) {
            $parentUuid = Uuid::fromBytes($property['parent_uuid']);
            $this->parentUuid = $parentUuid;
            $parentProperty = $this->fetchProperty($parentUuid);
            if (isset($parentProperty['parent_uuid'])) {
                $rootUuid = Uuid::fromBytes($parentProperty['parent_uuid']);
            } else {
                $rootUuid = $parentUuid;
            }

            $uuid = $rootUuid;
        }

        $objectClasses = ['host', 'service', 'notification', 'command', 'user'];
        $usage = [];

        foreach ($objectClasses as $objectClass) {
            $customPropertyQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iov' => "icinga_$objectClass" . '_var'], "io.id = iov.$objectClass" . '_id', [])
                ->join(['dp' => 'director_property'], 'iov.property_uuid = dp.uuid', []);

            $unionQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iop' => "icinga_$objectClass" . '_property'], "iop.$objectClass" . '_uuid = io.uuid', [])
                ->join(['dp' => 'director_property'], 'iop.property_uuid = dp.uuid', []);

            $columns = [
                'name' => 'io.object_name',
                'type' => 'io.object_type',
                'object_class' => new Zend_Db_Expr("'$objectClass'")
            ];

            if ($objectClass === 'service') {
                $customPropertyQuery = $customPropertyQuery->joinLeft(
                    ['ioh' => 'icinga_host'],
                    'io.host_id = ioh.id',
                    []
                );
                $unionQuery = $unionQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $columns['host_name'] = 'ioh.object_name';
            }

            $customPropertyQuery = $customPropertyQuery
                ->columns($columns)
                ->where('dp.uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

            $unionQuery = $unionQuery
                ->columns($columns)
                ->where('dp.uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

            $usage[] = $db->fetchAll($db->select()->union([$customPropertyQuery, $unionQuery]));
        }

        return array_merge(...$usage);
    }

    private function showFields(string $type): bool
    {
        return in_array($type, ['fixed-array', 'fixed-dictionary', 'dynamic-dictionary'], true);
    }

    public function addFieldAction(): void
    {
        $uuid = $this->uuid;
        $this->addTitleTab($this->translate('Create Field'));
        $uuid = Uuid::fromString($uuid);

        $parent = $this->fetchProperty($uuid);
        $propertyForm = (new CustomVariableForm($this->db, null, true, $uuid))
            ->setHideKeyNameElement($parent['value_type'] === 'fixed-array')
            ->setIsNestedField($parent['parent_uuid'] !== null)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(CustomVariableForm::ON_SUBMIT, function (CustomVariableForm $form) {
                Notification::success(sprintf(
                    $this->translate('Custom variable configuration "%s" has successfully been saved'),
                    $form->getValue('key_name')
                ));

                $this->sendExtraUpdates(['#col1']);
                $this->redirectNow(
                    Url::fromPath('director/customvar', ['uuid' => $form->getParentUUid()->toString()])
                );
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($propertyForm);
    }

    public function deleteAction(): void
    {
        $uuid = $this->uuid;
        $property = $this->fetchProperty($uuid);
        $parent = [];
        if ($property['parent_uuid'] !== null) {
            $parent = $this->fetchProperty(Uuid::fromBytes($property['parent_uuid']));
        }

        $form = (new DeleteCustomVariableForm($this->db, $property, $parent))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(DeleteCustomVariableForm::ON_SUBMIT, function () {
                Notification::success($this->translate('Custom variable configuration has been successfully deleted'));
                $this->sendExtraUpdates(['#col1']);
                $this->redirectNow('__CLOSE__');
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);

        $this->setTitle($this->translate('Delete Property') . ': ' . $property['key_name']);
    }

    public function variantsAction()
    {
        $varName = $this->params->getRequired('name');
        $title = sprintf($this->translate('Custom Variable variants: %s'), $varName);
        $this->setTitle($title);
        $this->addTitleTab($title);
        $this->addContent(CustomvarVariantsTable::create($this->db, $varName));
    }

    /**
     * Fetch property for the given UUID
     *
     * @param UuidInterface $uuid UUID of the given property
     *
     * @return array<string, mixed>
     */
    private function fetchProperty(UuidInterface $uuid): array
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'key_name',
                'uuid',
                'parent_uuid',
                'category_id',
                'value_type',
                'label',
                'description'
            ])
            ->where('uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        return Db\DbUtil::normalizeRow($db->fetchRow($query, [], Zend_Db::FETCH_ASSOC) ?: []);
    }

    private function fetchPropertyUsedCount(UuidInterface $uuid): int
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->joinLeft(['isp' => 'icinga_service_property'], 'isp.property_uuid = dp.uuid', [])
            ->joinLeft(['iup' => 'icinga_user_property'], 'iup.property_uuid = dp.uuid', [])
            ->joinLeft(['icp' => 'icinga_command_property'], 'icp.property_uuid = dp.uuid', [])
            ->joinLeft(['inp' => 'icinga_notification_property'], 'inp.property_uuid = dp.uuid', [])
            ->columns([
                'used_count' => 'COUNT(ihp.property_uuid) + COUNT(isp.property_uuid)'
                    . ' + COUNT(iup.property_uuid) + COUNT(icp.property_uuid)'
                    . ' + COUNT(inp.property_uuid)'
            ])
            ->where('uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        return (int) $db->fetchOne($query);
    }

    /**
     * Sets the active tab in the interface based on the provided tab name.
     *
     * @param string $name The name of the tab to activate.
     *
     * @return void
     */
    protected function setTitleTab(string $name): void
    {
        $tab = $this->createTabs()->get($name);

        if ($tab !== null) {
            $this->getTabs()->activate($name);
        }
    }

    /**
     * Create the tabs for the custom variable page.
     *
     * @return Tabs
     */
    protected function createTabs(): Tabs
    {
        $url = Url::fromPath('director/customvar', ['uuid' => $this->uuid->toString()]);
        if ($this->parentUuid) {
            $url->addParams(['parent_uuid' => $this->parentUuid->toString()]);
            $label = $this->translate('Edit Field');
        } else {
            $label = $this->translate('Custom Variable');
        }

        return $this->getTabs()
            ->add('customvar', [
                'label' => $label,
                'url' => $url
            ])
            ->add('usage', [
                'label' => $this->translate('Custom Variable Usage'),
                'url' => Url::fromPath(
                    'director/customvar/usage',
                    ['uuid' => $this->uuid->toString()]
                )
            ]);
    }
}
