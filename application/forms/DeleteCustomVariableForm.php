<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
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
        protected Db $db,
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

        $uuid = DbUtil::quoteBinaryCompat($uuid, $db);

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
                'object_class' => new Zend_Db_Expr("'$objectClass'"),
                'type' => 'io.object_type'
            ];

            if ($objectClass === 'service') {
                $customPropertyQuery = $customPropertyQuery
                    ->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $unionQuery = $unionQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $columns['host_name'] = 'ioh.object_name';
            }

            $customPropertyQuery = $customPropertyQuery->columns($columns)
                                                       ->where('dp.uuid = ?', $uuid);

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
                    $info = sprintf(
                        $this->translate(
                            'Deleting this sub field from custom variable "%s" will remove this field in'
                            . ' the corresponding custom variables from the below templates and objects.'
                            . ' Are you sure you want to delete it?'
                        ),
                        $this->fetchProperty(Uuid::fromBytes($this->parent['parent_uuid']))['key_name']
                    );
                } else {
                    $info = sprintf($this->translate(
                        'Deleting this field from custom variable "%s" will remove this field in'
                        . ' the corresponding custom variable from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->parent['key_name']);
                }
            } else {
                $info = $this->translate(
                    'Deleting this custom variable will remove it from the below templates and'
                    . ' objects. Are you sure you want to delete it?'
                );
            }
        } else {
            if ($this->parent) {
                $info = $this->translate('The field is not in use and hence can be safely deleted.');
            } else {
                $info = $this->translate('The custom variable is not in use and hence can be safely deleted.');
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
            function (ListItem $item, $data) use (&$objectClass, $usageList) {
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
            ->where('uuid = ?', DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        return DbUtil::normalizeRow($db->fetchRow($query, [], Zend_Db::FETCH_ASSOC) ?: []);
    }

    /**
     * Find the root property by following parent_uuid up from $parent, collecting
     * key_names along the way. Dictionaries can nest arbitrarily deep, so the root isn't
     * always $parent's direct parent.
     *
     * @param array $property The property being deleted
     * @param array $parent   The immediate parent of the property being deleted
     *
     * @return array{0: array<string, mixed>, 1: string[]} [$rootProperty, $pathWithinRootValue]
     *         $pathWithinRootValue lists the key_names from directly under the root down to
     *         (and including) $property['key_name'].
     */
    private function resolveRootProperty(array $property, array $parent): array
    {
        $path = [$property['key_name']];
        $current = $parent;

        while ($current['parent_uuid'] !== null) {
            array_unshift($path, $current['key_name']);
            $current = $this->fetchProperty(Uuid::fromBytes($current['parent_uuid']));
        }

        return [$current, $path];
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
        } elseif (isset($item[$key]) && is_array($item[$key])) {
            $this->removeDictionaryItem($item[$key], $path);
        }

        // Remove empty arrays (but not scalar zero/false values)
        if (isset($item[$key]) && is_array($item[$key]) && empty($item[$key])) {
            unset($item[$key]);
        }
    }

    /**
     * Strip the given path out of every entry in a dynamic dictionary, in place. Entries
     * that aren't arrays are left alone, and it's up to the caller to decide what to do
     * with an entry that ends up empty afterwards.
     *
     * @param array $dynamicDictionaryValue The dynamic dictionary's entries, modified in place
     * @param string[] $path The path (relative to each entry) to strip
     *
     * @return void
     */
    private function removeDictionaryItemFromEveryEntry(array &$dynamicDictionaryValue, array $path): void
    {
        foreach ($dynamicDictionaryValue as $entryKey => $entryValue) {
            if (! is_array($entryValue)) {
                continue;
            }

            $this->removeDictionaryItem($dynamicDictionaryValue[$entryKey], $path);
        }
    }

    protected function onSuccess(): void
    {
        $uuid = $this->property['uuid'];
        $quotedUuid = DbUtil::quoteBinaryCompat($uuid, $this->db->getDbAdapter());
        $db = $this->db;
        $prop = $this->property;

        // A dictionary can be nested arbitrarily deep (dictionary -> dictionary -> ...),
        // and any level of that nesting might itself be a datalist-backed field. Hence,
        // any links between datalists and children or grandchildren in the hierarchy must
        // also be removed.
        $allUuids = array_merge([$uuid], $this->collectDescendantUuids($uuid));
        $quotedAllUuids = DbUtil::quoteBinaryCompat($allUuids, $this->db->getDbAdapter());

        $db->runFailSafeTransaction(function () use ($db, $prop, $quotedUuid, $quotedAllUuids) {
            $db->delete('director_property_datalist', Filter::where('property_uuid', $quotedAllUuids));

            $this->removeObjectCustomVars($prop, $this->parent);
            $this->removeFromOverrideServiceVars($prop, $this->parent);

            $objects = ['host', 'service', 'notification', 'command', 'user', 'service_set'];
            foreach ($objects as $object) {
                $this->db->delete("icinga_{$object}_var", Filter::where('property_uuid', $quotedUuid));
            }

            $db->delete('director_property', Filter::where('uuid', $quotedAllUuids));
        });
    }

    /**
     * Recursively collect the raw binary UUIDs of all descendants (children and
     * grandchildren) of the property with the given raw binary UUID.
     *
     * @param string $uuid Raw binary UUID of the property to start from
     *
     * @return string[] Raw binary UUIDs of all descendants, not including $uuid itself
     */
    private function collectDescendantUuids(string $uuid): array
    {
        $dba = $this->db->getDbAdapter();
        $descendants = [];
        $parents = [$uuid];

        while (! empty($parents)) {
            $children = $dba->fetchCol(
                $dba->select()
                    ->from('director_property', ['uuid'])
                    ->where('parent_uuid IN (?)', DbUtil::quoteBinaryCompat($parents, $dba))
            );
            $children = array_map([DbUtil::class, 'binaryResult'], $children);

            $descendants[] = $children;
            $parents = $children;
        }

        return array_merge(...$descendants);
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
        } else {
            [$rootProp, $pathWithinRootValue] = $this->resolveRootProperty($property, $parent);
            $rootKeyName = $rootProp['key_name'];
            $rootType = $rootProp['value_type'];
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
                    // Dynamic dictionary: remove the path from every dynamic entry, dropping
                    // any entry that becomes empty as a result
                    if (is_array($serviceVars[$rootKeyName])) {
                        $this->removeDictionaryItemFromEveryEntry($serviceVars[$rootKeyName], $pathWithinRootValue);
                        foreach ($serviceVars[$rootKeyName] as $entryKey => $entryValue) {
                            if ($entryValue === []) {
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
        $parentUuid = Uuid::fromBytes($parent['uuid']);

        // Path within the stored JSON to the key being deleted — constant for all rows
        [$rootProp, $path] = $this->resolveRootProperty($property, $parent);
        $rootUuid = Uuid::fromBytes($rootProp['uuid']);
        $rootType = $rootProp['value_type'];

        // Re-index the fixed-array items in director_property once, before processing stored vars
        $isParentFixedArray = $parent['value_type'] === 'fixed-array';
        $isRootFixedArray = $rootType === 'fixed-array';
        if ($isParentFixedArray) {
            $this->updateFixedArrayItems($parentUuid, $property['key_name']);
        }

        foreach (['host', 'service', 'notification', 'command', 'user'] as $objectType) {
            $idColumn = "{$objectType}_id";
            $varRows = $db->fetchAll(
                $db->select()
                   ->from(['iov' => "icinga_{$objectType}_var"], [])
                   ->columns([$idColumn, 'varname', 'varvalue'])
                   ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $db)),
                [],
                Zend_Db::FETCH_ASSOC
            );

            $objectClass = DbObjectTypeRegistry::classByType($objectType);

            foreach ($varRows as $varRow) {
                $varValue = json_decode($varRow['varvalue'], true);

                if ($rootType !== 'dynamic-dictionary') {
                    $this->removeDictionaryItem($varValue, $path);
                } else {
                    $this->removeDictionaryItemFromEveryEntry($varValue, $path);
                    foreach ($varValue as $entryKey => $entryValue) {
                        if ($entryValue === []) {
                            $varValue[$entryKey] = (object) [];
                        }
                    }
                }

                $object = $objectClass::loadWithAutoIncId($varRow[$idColumn], $this->db);
                $vars = $object->vars();

                if (empty($varValue)) {
                    $vars->set($varRow['varname'], null);
                    $vars->storeToDb($object);

                    continue;
                }

                if ($isParentFixedArray && $rootUuid->equals($parentUuid)) {
                    // The fixed array is the root: the stored value is the bare list itself.
                    $varValue = array_values($varValue);
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
     * @param string $propertyIndex
     *
     * @return void
     */
    private function updateFixedArrayItems(UuidInterface $uuid, string $propertyIndex): void
    {
        $db = $this->db->getDbAdapter();
        $quotedUuid = DbUtil::quoteBinaryCompat($uuid->getBytes(), $db);

        // Delete the item being removed first, freeing up its key_name slot before any
        // surviving sibling is renumbered into it — key_name is unique per parent_uuid.
        $db->delete(
            'director_property',
            [
                'parent_uuid = ?' => $quotedUuid,
                'key_name = ?' => $propertyIndex,
            ]
        );

        $propItems = array_map(
            [DbUtil::class, 'normalizeRow'],
            $db->fetchAll(
                $db->select()
                    ->from('director_property', ['uuid', 'key_name'])
                    ->where('parent_uuid = ?', $quotedUuid),
                [],
                Zend_Db::FETCH_ASSOC
            )
        );
        // key_name is a varchar column; a fixed array's item indexes must be sorted numerically
        // here, not lexicographically (otherwise '10' would sort before '2').
        usort($propItems, fn($a, $b) => (int) $a['key_name'] <=> (int) $b['key_name']);

        // Renumber survivors in place — everything else about each item's row (category,
        // label, value_type, description, director_property_datalist link, and any other
        // table referencing its uuid, such as icinga_host_property) is left untouched.
        foreach ($propItems as $index => $propItem) {
            if ($propItem['key_name'] === (string) $index) {
                continue;
            }

            $db->update(
                'director_property',
                ['key_name' => $index],
                $db->quoteInto(
                    'uuid = ?',
                    DbUtil::quoteBinaryCompat($propItem['uuid'], $db)
                )
            );
        }
    }
}
