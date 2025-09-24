<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\DeletePropertyForm;
use Icinga\Module\Director\Forms\PropertyForm;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Module\Director\Web\Widget\PropertyTable;
use Icinga\Web\Notification;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Web\Compat\CompatController;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use ipl\Web\Widget\EmptyStateBar;
use ipl\Web\Widget\Tabs;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;

class PropertyController extends CompatController
{
    /** @var Db */
    protected $db;

    /** @var UuidInterface */
    private UuidInterface $uuid;

    /** @var ?UuidInterface */
    private ?UuidInterface $parentUuid = null;

    public function init()
    {
        parent::init();

        $this->uuid = Uuid::fromString($this->params->shiftRequired('uuid'));
        $parentUuid = $this->params->shift('parent_uuid');

        if ($parentUuid) {
            $this->parentUuid = Uuid::fromString($parentUuid);
        }

        $this->db = Db::fromResourceName(
            Config::module('director')->get('db', 'resource')
        );
    }

    public function indexAction()
    {
        $uuid = $this->uuid;
        $parentUuid = $this->parentUuid ?? null;
        $parent = [];
        $db = $this->db->getDbAdapter();
        $property = $this->fetchProperty($uuid);

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

        if ($property['value_type'] === 'dynamic-array') {
            $itemTypeQuery = $db
                ->select()->from('director_property', 'value_type')
                ->where(
                    'parent_uuid = ? AND key_name = \'0\'',
                    $uuid->getBytes()
                );

            $property['item_type'] = $db->fetchOne($itemTypeQuery);
        }

        $showFields = $this->showFields($property['value_type']);
        $propertyForm = (new PropertyForm($this->db, $uuid, $parentUuid !== null, $parentUuid))
            ->populate($property)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(PropertyForm::ON_SENT, function (PropertyForm $form) use ($property, &$showFields) {
                $showFields = $showFields && $form->getValue('value_type') === $property['value_type'];
            })
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                Notification::success(sprintf(
                    $this->translate('Property "%s" has successfully been saved'),
                    $form->getValue('key_name')
                ));

                $this->sendExtraUpdates(['#col1']);
                $redirectUrl = Url::fromPath(
                    'director/property',
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
                Text::create($this->translate('Add Field')),
                Url::fromPath('director/property/add-field', [
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
                    'value_type',
                    'label',
                    'description',
                    'used_count' => $property['used_count'] > 0 ? 'COUNT(1)' : '0',
                ])
                ->where('parent_uuid = ?', $uuid->getBytes())
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
                $this->addContent(new PropertyTable($fields, true));
            }
        }

        if ($parentUuid) {
            $keyName = $parent['value_type'] === 'fixed-array'
                ? $property['label']
                : $property['key_name'];

            $title = $this->translate('Edit Field') . ': ' . $keyName;
        } else {
            $title = $this->translate('Property') . ': ' . $property['key_name'];
        }

        $this->setTitle($title);
        $this->setTitleTab('property');
    }

    public function usageAction(): void
    {
        $uuid = $this->uuid;
        $property = $this->fetchProperty($uuid);
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

        $db = $this->db->getDbAdapter();

        $customPropQuery = $db
            ->select()
            ->from(['ih' => 'icinga_host'], [])
            ->join(['ihv' => 'icinga_host_var'], 'ih.id = ihv.host_id', [])
            ->join(['dp' => 'director_property'], 'ihv.property_uuid = dp.uuid', [])
            ->columns([
                'name' => 'ih.object_name',
                'type' => 'ih.object_type'
            ])
            ->where('dp.uuid = ?', $uuid->getBytes());

        $unionQuery = $db
            ->select()
            ->from(['ih' => 'icinga_host'], [])
            ->join(['ihp' => 'icinga_host_property'], 'ihp.host_uuid = ih.uuid', [])
            ->join(['dp' => 'director_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'name' => 'ih.object_name',
                'type' => 'ih.object_type'
            ])
            ->where('ihp.property_uuid = ?', $uuid->getBytes());
        $this->addContent(
            (new CustomVarObjectList($db->fetchAll($db->select()->union([$customPropQuery, $unionQuery]))))
                ->setDetailActionsDisabled(false)
        );

        $this->setTitle($this->translate('Custom Variable Usage'));
        $this->setTitleTab('usage');
    }

    private function showFields(string $type): bool
    {
        return in_array($type, ['fixed-array', 'fixed-dictionary', 'dynamic-dictionary'], true);
    }

    public function addFieldAction()
    {
        $uuid = $this->uuid;
        $this->addTitleTab($this->translate('Add Field'));
        $uuid = Uuid::fromString($uuid);

        $parent = $this->fetchProperty($uuid);
        $propertyForm = (new PropertyForm($this->db, null, true, $uuid))
            ->setHideKeyNameElement($parent['value_type'] === 'fixed-array')
            ->setIsNestedField($parent['parent_uuid'] !== null)
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(PropertyForm::ON_SUCCESS, function (PropertyForm $form) {
                Notification::success(sprintf(
                    $this->translate('Property "%s" has successfully been saved'),
                    $form->getValue('key_name')
                ));

                $this->sendExtraUpdates(['#col1']);
                $this->redirectNow(
                    Url::fromPath('director/property', ['uuid' => $form->getParentUUid()->toString()])
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

        $form = (new DeletePropertyForm($this->db, $property, $parent))
            ->setAction(Url::fromRequest()->getAbsoluteUrl())
            ->on(DeletePropertyForm::ON_SUCCESS, function () {
                Notification::success($this->translate('Property has successfully been deleted'));
                $this->sendExtraUpdates(['#col1']);
                $this->redirectNow('__CLOSE__');
            })
            ->handleRequest($this->getServerRequest());

        $this->addContent($form);

        $this->setTitle($this->translate('Delete Property') . ': ' . $property['key_name']);
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
                'value_type',
                'label',
                'description'
            ])
            ->where('uuid = ?', $uuid->getBytes());

        return $db->fetchRow($query, [], Zend_Db::FETCH_ASSOC);
    }

    private function fetchPropertyUsedCount(UuidInterface $uuid): int
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'used_count' => 'COUNT(ihp.property_uuid)'
            ])
            ->where('uuid = ?', $uuid->getBytes());

        return (int) $db->fetchOne($query);
    }

    protected function setTitleTab(string $name): void
    {
        $tab = $this->createTabs()->get($name);

        if ($tab !== null) {
            $this->getTabs()->activate($name);
        }
    }

    protected function createTabs(): Tabs
    {
        $url = Url::fromPath('director/property', ['uuid' => $this->uuid->toString()]);
        if ($this->parentUuid) {
            $url->addParams(['parent_uuid' => $this->parentUuid->toString()]);
            $label = $this->translate('Edit Field');
        } else {
            $label = $this->translate('Property');
        }

        $tabs = $this->getTabs()
             ->add('property', [
                 'label'  => $label,
                 'url'    => $url
             ])
             ->add('usage', [
                 'label'  => $this->translate('Custom Variable Usage'),
                 'url'    => Url::fromPath('director/property/usage', ['uuid' => $this->uuid->toString()])
             ]);

        return $tabs;
    }
}
