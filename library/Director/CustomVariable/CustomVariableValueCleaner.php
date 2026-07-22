<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\DbUtil;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;

/**
 * Keeps stored custom variable values in sync when a property's schema loses a nested key.
 */
class CustomVariableValueCleaner
{
    public function __construct(protected DbConnection $db)
    {
    }

    /**
     * Fetch property for the given UUID
     *
     * @return array<string, mixed>
     */
    public function fetchProperty(UuidInterface $uuid): array
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
     * Walk parent_uuid up from $parent to the root, collecting key_names along the way.
     *
     * @return array{0: array<string, mixed>, 1: string[]}
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
     * Remove dictionary item from the given data array
     *
     * @param bool $preserveIndex null out the terminal key instead of unsetting it, so a
     *                             fixed-array's sibling positions don't shift
     *
     * @return void
     */
    private function removeDictionaryItem(array &$item, array $path, bool $preserveIndex = false): void
    {
        $key = array_shift($path);

        if (! array_key_exists($key, $item)) {
            return;
        }

        if (empty($path)) {
            if ($preserveIndex) {
                $item[$key] = null;
            } else {
                unset($item[$key]);
            }
        } elseif (isset($item[$key]) && is_array($item[$key])) {
            $this->removeDictionaryItem($item[$key], $path, $preserveIndex);
        }

        if (! $preserveIndex && isset($item[$key]) && is_array($item[$key]) && empty($item[$key])) {
            unset($item[$key]);
        }
    }

    /**
     * Strip $path out of every entry in a dynamic dictionary, in place.
     *
     * @return void
     */
    private function removeDictionaryItemFromEveryEntry(
        array &$dynamicDictionaryValue,
        array $path,
        bool $preserveIndex = false
    ): void {
        foreach ($dynamicDictionaryValue as $entryKey => $entryValue) {
            if (! is_array($entryValue)) {
                continue;
            }

            $this->removeDictionaryItem($dynamicDictionaryValue[$entryKey], $path, $preserveIndex);
        }
    }

    /**
     * Strip $property's value out of every host's, service's, etc. stored custom variables.
     * No-op for a root property (empty $parent), use deleteStoredValues() for that case.
     *
     * @param bool $keepPropertyInPlace true when $property is only being retyped, not removed
     *
     * @return void
     */
    public function removeObjectCustomVars(
        array $property,
        ?array $parent = null,
        bool $keepPropertyInPlace = false
    ): void {
        if (empty($parent)) {
            return;
        }

        $db = $this->db->getDbAdapter();
        $parentUuid = Uuid::fromBytes($parent['uuid']);

        [$rootProp, $path] = $this->resolveRootProperty($property, $parent);
        $rootUuid = Uuid::fromBytes($rootProp['uuid']);
        $rootType = $rootProp['value_type'];

        $isParentFixedArray = $parent['value_type'] === 'fixed-array';
        $isRootFixedArray = $rootType === 'fixed-array';
        $preserveIndex = $keepPropertyInPlace && $isParentFixedArray;

        // A retyped-in-place property keeps its slot, only a removed one needs its
        // fixed-array siblings renumbered.
        if (! $keepPropertyInPlace && $isParentFixedArray) {
            $this->updateFixedArrayItems($parentUuid, $property['key_name']);
        }

        foreach (['host', 'service', 'notification', 'command', 'user'] as $objectType) {
            $idColumn = "{$objectType}_id";
            // Match by varname, not property_uuid, root key_names are unique and property_uuid
            // is only ever an optional hint that isn't reliably populated on every stored row.
            $varRows = $db->fetchAll(
                $db->select()
                   ->from(['iov' => "icinga_{$objectType}_var"], [])
                   ->columns([$idColumn, 'varname', 'varvalue'])
                   ->where('varname = ?', $rootProp['key_name']),
                [],
                Zend_Db::FETCH_ASSOC
            );

            $objectClass = DbObjectTypeRegistry::classByType($objectType);

            foreach ($varRows as $varRow) {
                $varValue = json_decode($varRow['varvalue'], true);

                if ($rootType !== 'dynamic-dictionary') {
                    $this->removeDictionaryItem($varValue, $path, $preserveIndex);
                } else {
                    $this->removeDictionaryItemFromEveryEntry($varValue, $path, $preserveIndex);
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

                if (
                    ! $preserveIndex
                    && ($isRootFixedArray || ($isParentFixedArray && $rootUuid->equals($parentUuid)))
                ) {
                    $varValue = array_values($varValue);
                }

                $vars->set($varRow['varname'], $varValue);
                $vars->storeToDb($object);
            }
        }
    }

    /**
     * Strip $property's value out of every host's _override_servicevars custom variable.
     *
     * @param bool $keepPropertyInPlace see removeObjectCustomVars()
     *
     * @return void
     */
    public function removeFromOverrideServiceVars(
        array $property,
        array $parent,
        bool $keepPropertyInPlace = false
    ): void {
        $db = $this->db->getDbAdapter();

        $overrideVarname = $db->fetchOne(
            $db->select()
               ->from('director_setting', ['setting_value'])
               ->where('setting_name = ?', 'override_services_varname')
        ) ?: '_override_servicevars';

        if (empty($parent)) {
            $rootKeyName = $property['key_name'];
            $rootType = $property['value_type'];
            $pathWithinRootValue = null;
            $preserveIndex = false;
        } else {
            [$rootProp, $pathWithinRootValue] = $this->resolveRootProperty($property, $parent);
            $rootKeyName = $rootProp['key_name'];
            $rootType = $rootProp['value_type'];
            $preserveIndex = $keepPropertyInPlace && $parent['value_type'] === 'fixed-array';
        }

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
                    unset($serviceVars[$rootKeyName]);
                } elseif ($rootType === 'dynamic-dictionary') {
                    if (is_array($serviceVars[$rootKeyName])) {
                        $this->removeDictionaryItemFromEveryEntry(
                            $serviceVars[$rootKeyName],
                            $pathWithinRootValue,
                            $preserveIndex
                        );
                        if (! $preserveIndex) {
                            foreach ($serviceVars[$rootKeyName] as $entryKey => $entryValue) {
                                if ($entryValue === []) {
                                    unset($serviceVars[$rootKeyName][$entryKey]);
                                }
                            }
                        }
                    }

                    if (! $preserveIndex && empty($serviceVars[$rootKeyName])) {
                        unset($serviceVars[$rootKeyName]);
                    }
                } else {
                    $this->removeDictionaryItem($serviceVars[$rootKeyName], $pathWithinRootValue, $preserveIndex);
                    if (! $preserveIndex && empty($serviceVars[$rootKeyName])) {
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

    /**
     * Drop every stored value for a root property outright, across every object type.
     *
     * @param string $varname the root property's own key_name. Only call this for a root
     *                        property, a nested property's key_name isn't guaranteed unique
     *                        and could collide with an unrelated root variable
     *
     * @return void
     */
    public function deleteStoredValues(string $varname): void
    {
        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $this->db->delete("icinga_{$object}_var", Filter::where('varname', $varname));
        }
    }

    /**
     * Update the items for the given fixed array
     *
     * @return void
     */
    private function updateFixedArrayItems(UuidInterface $uuid, string $propertyIndex): void
    {
        $db = $this->db->getDbAdapter();
        $quotedUuid = DbUtil::quoteBinaryCompat($uuid->getBytes(), $db);

        // Delete first so a renumbered survivor can't collide with this key_name.
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
        // Sort numerically, not lexicographically ('10' would otherwise sort before '2').
        usort($propItems, fn($a, $b) => (int) $a['key_name'] <=> (int) $b['key_name']);

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
