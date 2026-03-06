<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\ListItem;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;
use Zend_Db_Expr;

class DeleteCustomVariableForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether to hide the key name element or not (checked for the fixed array) */
    private $hideKeyNameElement = false;

    /** @var bool Whether the field is a nested field or not */
    private $isNestedField = false;

    public function __construct(
        protected DbConnection $db,
        protected array $property,
        protected array $parent = []
    ) {
    }

    /**
     * Fetch the give custom variable usage in templates
     *
     * @return array
     */
    private function fetchCustomVarUsage(): array
    {
        $db = $this->db->getDbAdapter();
        if ($this->parent) {
            if ($this->parent['parent_uuid'] !== null) {
                $uuid = $this->parent['parent_uuid'];
            } else {
                $uuid = $this->parent['uuid'];
            }
        } else {
            $uuid = $this->property['uuid'];
        }

        $objectClasses = ['host', 'service', 'notification', 'command', 'user'];
        $usage = [];

        foreach ($objectClasses as $objectClass) {
            $customPropertyQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iov' => "icinga_$objectClass" .'_var'], "io.id = iov.$objectClass" . '_id', [])
                ->join(['dp' => 'director_property'], 'iov.property_uuid = dp.uuid', []);

            $unionQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iop' => "icinga_$objectClass" . '_property'], "iop.$objectClass" . '_uuid = io.uuid', [])
                ->join(['dp' => 'director_property'], 'iop.property_uuid = dp.uuid', []);

            $columns = [
                'name' => 'io.object_name',
                'object_class' => new Zend_Db_Expr("'$objectClass'"),
                'type' => 'io.object_type'
            ];

            if ($objectClass === 'service') {
                $customPropertyQuery = $customPropertyQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $unionQuery = $unionQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $columns['host_name'] = 'ioh.object_name';
            }

            $customPropertyQuery = $customPropertyQuery->columns($columns)
                                                       ->where('dp.uuid = ?', Uuid::fromBytes($uuid)->getBytes());


            $unionQuery = $unionQuery->columns($columns)
                                     ->where('dp.uuid = ?', $uuid);

            $usage[] = $db->fetchAll($db->select()->union([$customPropertyQuery, $unionQuery]));
        }

        return array_merge(...$usage);
    }

    protected function assemble(): void
    {
        $customVarUsage = $this->fetchCustomVarUsage();
        if (count($customVarUsage) > 0) {
            if ($this->parent) {
                if ($this->parent['parent_uuid'] !== null) {
                    $info = sprintf($this->translate(
                        'Deleting this sub field from custom property "%s" will remove this field in'
                        . ' the corresponding custom variables from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->fetchProperty(Uuid::fromBytes($this->parent['parent_uuid']))['key_name']);
                } else {
                    $info = sprintf($this->translate(
                        'Deleting this field from custom property "%s" will remove this field in'
                        . ' the corresponding custom variables from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->parent['key_name']);
                }
            } else {
                $info = $this->translate(
                    'Deleting this custom property will remove the corresponding custom variable'
                    . ' from the below templates and objects. Are you sure you want to delete it?'
                );
            }
        } else {
            if ($this->parent) {
                $info = $this->translate('The field is not in use and hence can be safely deleted.');
            } else {
                $info = $this->translate('The custom property is not in use and hence can be safely deleted.');
            }
        }

        $this->addHtml(new HtmlElement(
            'div',
            Attributes::create(['class' => 'form-description']),
            new Icon('info-circle', ['class' => 'form-description-icon']),
            new HtmlElement(
                'ul',
                null,
                new HtmlElement('li', null, Text::create($info))
            )
        ));

        $objectClass = null;
        $usageList = (new CustomVarObjectList($customVarUsage));
        $usageList->on(
            CustomVarObjectList::BEFORE_ITEM_ADD,
            function (ListItem $item, $data) use(&$objectClass, $usageList) {
                if ($objectClass !== $data->object_class) {
                    $usageList->addHtml(HtmlElement::create(
                        'li',
                        ['class' => 'list-item'],
                        HtmlElement::create(
                            'h2',
                            content: ucfirst($data->object_class) . 's'
                        )
                    ));
                    $objectClass = $data->object_class;
                }
            });

        $this->addHtml($usageList);

        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Delete'),
            'class' => 'btn-remove'
        ]);
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

    /**
     * Remove dictionary item from the give data array
     *
     * @param array $item
     * @param array $path
     *
     * @return void
     */
    private function removeDictionaryItem(array &$item, array $path): void
    {
        $key = array_shift($path);

        if (! array_key_exists($key, $item)) {
            return;
        }

        if (empty($path)) {
            unset($item[$key]);
        } elseif (is_array($item[$key])) {
            $this->removeDictionaryItem($item[$key], $path);
        }

        // Remove empty array items
        if (isset($item[$key]) && empty($item[$key])) {
            unset($item[$key]);
        }
    }

    protected function onSuccess(): void
    {
        $uuid = Uuid::fromBytes($this->property['uuid']);
        $db = $this->db;

        $db->getDbAdapter()->beginTransaction();
        $prop = $this->property;

        if (str_starts_with($prop['value_type'], 'datalist-')) {
            $db->delete('director_property_datalist', Filter::where('property_uuid', $uuid->getBytes()));
        }

        $this->removeObjectCustomVars($prop, $this->parent);
        $this->removeFromOverrideServiceVars($prop, $this->parent);

        $db->delete('director_property', Filter::where('uuid', $uuid->getBytes()));
        $db->delete('director_property', Filter::where('parent_uuid', $uuid->getBytes()));

        $objects = ['host', 'service', 'notification', 'command', 'user'];
        foreach ($objects as $object) {
            $this->db->delete("icinga_{$object}_var", Filter::where('property_uuid', $uuid->getBytes()));
        }

        $db->getDbAdapter()->commit();
    }

    /**
     * Remove the deleted property's key from all hosts' _override_servicevars custom variable
     *
     * @param array $property The deleted property
     * @param array $parent   The parent property (empty for root properties)
     *
     * @return void
     */
    private function removeFromOverrideServiceVars(array $property, array $parent): void
    {
        $db = $this->db->getDbAdapter();

        // Get the configured override varname, falling back to the default
        $overrideVarname = $db->fetchOne(
            $db->select()
               ->from('director_setting', ['setting_value'])
               ->where('setting_name = ?', 'override_services_varname')
        ) ?: '_override_servicevars';

        // Determine the root property key, root type, and path within each service's root-key value
        if (empty($parent)) {
            // Root property deleted: remove its key_name from each service's override vars
            $rootKeyName = $property['key_name'];
            $rootType = $property['value_type'];
            $pathWithinRootValue = null;
        } elseif ($parent['parent_uuid'] === null) {
            // Child field of a root property deleted
            $rootKeyName = $parent['key_name'];
            $rootType = $parent['value_type'];
            $pathWithinRootValue = [$property['key_name']];
        } else {
            // Nested child field deleted (grandparent is the root property)
            $rootProp = $this->fetchProperty(Uuid::fromBytes($parent['parent_uuid']));
            $rootKeyName = $rootProp['key_name'];
            $rootType = $rootProp['value_type'];
            $pathWithinRootValue = [$parent['key_name'], $property['key_name']];
        }

        // Fetch all hosts that have the _override_servicevars custom variable
        $query = $db->select()
                    ->from('icinga_host_var', ['host_id', 'varvalue'])
                    ->where('varname = ?', $overrideVarname);

        $rows = $db->fetchAll($query, [], Zend_Db::FETCH_ASSOC);

        foreach ($rows as $row) {
            $overrideVars = json_decode($row['varvalue'], true);
            if (! is_array($overrideVars)) {
                continue;
            }

            $modified = false;
            foreach ($overrideVars as $serviceName => $serviceVars) {
                if (! is_array($serviceVars) || ! array_key_exists($rootKeyName, $serviceVars)) {
                    continue;
                }

                $modified = true;

                if ($pathWithinRootValue === null) {
                    // Root property deleted: remove its key from the service's override vars
                    unset($serviceVars[$rootKeyName]);
                } elseif ($rootType === 'dynamic-dictionary') {
                    // Dynamic dictionary: remove the path from every dynamic entry
                    if (is_array($serviceVars[$rootKeyName])) {
                        foreach ($serviceVars[$rootKeyName] as $entryKey => $entryValue) {
                            if (! is_array($entryValue)) {
                                continue;
                            }

                            $this->removeDictionaryItem($serviceVars[$rootKeyName][$entryKey], $pathWithinRootValue);
                            if (empty($serviceVars[$rootKeyName][$entryKey])) {
                                unset($serviceVars[$rootKeyName][$entryKey]);
                            }
                        }
                    }

                    if (empty($serviceVars[$rootKeyName])) {
                        unset($serviceVars[$rootKeyName]);
                    }
                } else {
                    // Fixed/static type: remove the nested path within the root key's value
                    $this->removeDictionaryItem($serviceVars[$rootKeyName], $pathWithinRootValue);
                    if (empty($serviceVars[$rootKeyName])) {
                        unset($serviceVars[$rootKeyName]);
                    }
                }

                if (empty($serviceVars)) {
                    unset($overrideVars[$serviceName]);
                } else {
                    $overrideVars[$serviceName] = $serviceVars;
                }
            }

            if (! $modified) {
                continue;
            }

            if (empty($overrideVars)) {
                $db->delete('icinga_host_var', [
                    'host_id = ?' => $row['host_id'],
                    'varname = ?'  => $overrideVarname,
                ]);
            } else {
                $db->update(
                    'icinga_host_var',
                    ['varvalue' => json_encode($overrideVars)],
                    [
                        'host_id = ?' => $row['host_id'],
                        'varname = ?'  => $overrideVarname,
                    ]
                );
            }
        }
    }

    private function removeObjectCustomVars(array $property, ?array $parent = null): void
    {
        if (empty($parent)) {
            return;
        }

        $db = $this->db->getDbAdapter();

        if ($parent['parent_uuid'] !== null) {
            // Parent is itself a field — grandparent is the root property
            $rootUuid = Uuid::fromBytes($parent['parent_uuid']);
            $rootProp = $this->fetchProperty($rootUuid);
            $rootType = $rootProp['value_type'];
        } else {
            $rootUuid = Uuid::fromBytes($parent['uuid']);
            $rootType = $parent['value_type'];
        }

        // Path within the stored JSON to the key being deleted — constant for all rows
        $path = [$property['key_name']];
        if ($parent['parent_uuid'] !== null) {
            array_unshift($path, $parent['key_name']);
        }

        // Re-index the fixed-array items in director_property once, before processing stored vars
        $isParentFixedArray = $parent['value_type'] === 'fixed-array';
        $isRootFixedArray = $rootType === 'fixed-array';
        if ($isParentFixedArray) {
            $this->updateFixedArrayItems(Uuid::fromBytes($parent['uuid']));
        } elseif ($isRootFixedArray) {
            $this->updateFixedArrayItems($rootUuid);
        }

        foreach (['host', 'service', 'notification', 'command', 'user'] as $objectType) {
            $idColumn = "{$objectType}_id";
            $varRows = $db->fetchAll(
                $db->select()
                   ->from(['iov' => "icinga_{$objectType}_var"], [])
                   ->columns([$idColumn, 'varname', 'varvalue'])
                   ->where('property_uuid = ?', $rootUuid->getBytes()),
                [],
                Zend_Db::FETCH_ASSOC
            );

            $objectClass = DbObjectTypeRegistry::classByType($objectType);

            foreach ($varRows as $varRow) {
                $varValue = json_decode($varRow['varvalue'], true);

                if ($rootType !== 'dynamic-dictionary') {
                    $this->removeDictionaryItem($varValue, $path);
                } else {
                    foreach ($varValue as $entryKey => $entryValue) {
                        $varValue[$entryKey] = (object) $entryValue;
                        $this->removeDictionaryItem($varValue, $path);
                    }
                }

                $object = $objectClass::loadWithAutoIncId($varRow[$idColumn], $this->db);
                $vars = $object->vars();

                if (empty($varValue)) {
                    $vars->set($varRow['varname'], null);

                    continue;
                }

                if ($isParentFixedArray) {
                    $varValue[$parent['key_name']] = array_values($varValue[$parent['key_name']]);
                } elseif ($isRootFixedArray) {
                    $varValue = array_values($varValue);
                }

                $vars->set($varRow['varname'], $varValue);
                $vars->storeToDb($object);
            }
        }
    }

    /**
     * Update the items for the given fixed array
     *
     * @param UuidInterface $uuid
     *
     * @return void
     */
    private function updateFixedArrayItems(UuidInterface $uuid): void
    {
        $db = $this->db->getDbAdapter();
        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->columns([
                'key_name',
                'uuid',
                'parent_uuid',
                'value_type',
                'label',
                'description'
            ])
            ->where('parent_uuid = ?', $uuid->getBytes());

        $propItems = $db->fetchAll($query, [], Zend_Db::FETCH_ASSOC);

        $db->delete(
            'director_property',
            ['parent_uuid = ?' => $uuid->getBytes()]
        );

        $count = 0;
        foreach ($propItems as $propItem) {
            $this->db->insert('director_property', [
                'uuid' => Uuid::fromBytes($propItem['uuid'])->getBytes(),
                'parent_uuid' => $uuid->getBytes(),
                'key_name' => $count,
                'label' => $propItem['label'],
                'value_type' => $propItem['value_type'],
                'description' => $propItem['description']
            ]);

            $count++;
        }
    }
}
