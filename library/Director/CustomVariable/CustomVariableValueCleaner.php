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
    public function resolveRootProperty(array $property, array $parent): array
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
        $key = array_shift($path) ?? '';

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
     * Move a value to a new key in a nested array, in place
     *
     * Only the last step of the path changes, so old and new walk down together.
     *
     * @return void
     */
    private function renameDictionaryItem(array &$item, array $oldPath, array $newPath): void
    {
        $oldKey = array_shift($oldPath);
        $newKey = array_shift($newPath);

        if (! array_key_exists($oldKey, $item)) {
            return;
        }

        if (empty($oldPath)) {
            $item[$newKey] = $item[$oldKey];
            if ($oldKey !== $newKey) {
                unset($item[$oldKey]);
            }

            return;
        }

        if (is_array($item[$oldKey])) {
            $this->renameDictionaryItem($item[$oldKey], $oldPath, $newPath);
        }
    }

    /**
     * Does the same move for every entry of a dynamic dictionary
     *
     * @return void
     */
    private function renameDictionaryItemInEveryEntry(
        array &$dynamicDictionaryValue,
        array $oldPath,
        array $newPath
    ): void {
        foreach ($dynamicDictionaryValue as $entryKey => $entryValue) {
            if (! is_array($entryValue)) {
                continue;
            }

            $this->renameDictionaryItem($dynamicDictionaryValue[$entryKey], $oldPath, $newPath);
        }
    }

    /**
     * Strip $property's value out of every host's, service's, etc. stored custom variables.
     * No-op for a root property (empty $parent), use deleteStoredValues() for that case.
     *
     * Leaves the stored values alone if a legacy Data Field owns the root ancestor's
     * varname, those values could be the field's, not this property's. The fixed-array
     * schema renumbering still happens either way, it's property schema only and has
     * nothing to do with the Data Field's data.
     *
     * @param bool $keepPropertyInPlace true when only $property's type is changing, not removed
     *
     * @return int Number of stored values left in place because of a legacy Data Field, 0
     *             if there was no conflict and the values were updated as usual
     */
    public function removeObjectCustomVars(
        array $property,
        ?array $parent = null,
        bool $keepPropertyInPlace = false
    ): int {
        if (empty($parent)) {
            return 0;
        }

        [$rootProp, $path] = $this->resolveRootProperty($property, $parent);

        $parentUuid = Uuid::fromBytes($parent['uuid']);
        $isParentFixedArray = $parent['value_type'] === 'fixed-array';

        // A property that only changes type in place keeps its slot, only a removed
        // one needs its fixed-array siblings renumbered. This is schema only, it has
        // nothing to do with the Data Field check below, so it always has to run.
        if (! $keepPropertyInPlace && $isParentFixedArray) {
            $this->updateFixedArrayItems($parentUuid, $property['key_name']);
        }

        if ($this->hasLegacyDatafield($rootProp['key_name'])) {
            return $this->countStoredValues($rootProp['key_name']);
        }

        $db = $this->db->getDbAdapter();
        $rootUuid = Uuid::fromBytes($rootProp['uuid']);
        $rootType = $rootProp['value_type'];
        $isRootFixedArray = $rootType === 'fixed-array';
        $preserveIndex = $keepPropertyInPlace && $isParentFixedArray;

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $objectType) {
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
                $varValue = json_decode($varRow['varvalue'] ?? '', true);

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

        return 0;
    }

    /**
     * Strip $property's value out of every host's _override_servicevars custom variable.
     *
     * Does nothing if a legacy Data Field owns the root ancestor's varname, same reasoning
     * as removeObjectCustomVars().
     *
     * @param bool $keepPropertyInPlace see removeObjectCustomVars()
     *
     * @return int Number of stored values left in place because of a legacy Data Field, 0
     *             if there was no conflict and the values were updated as usual
     */
    public function removeFromOverrideServiceVars(
        array $property,
        array $parent,
        bool $keepPropertyInPlace = false
    ): int {
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

        if ($this->hasLegacyDatafield($rootKeyName)) {
            return $this->countStoredValues($rootKeyName);
        }

        $db = $this->db->getDbAdapter();

        $overrideVarname = $db->fetchOne(
            $db->select()
               ->from('director_setting', ['setting_value'])
               ->where('setting_name = ?', 'override_services_varname')
        ) ?: '_override_servicevars';

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

        return 0;
    }

    /**
     * Drop every stored value for a root property outright, across every object type.
     *
     * Does nothing if a legacy Data Field still exists under the same varname, a value
     * migration deliberately left behind can still be that field's live data, not this
     * property's. The admin has to rename or remove that field first, then delete this
     * property again to actually clear it.
     *
     * @param string $varname the root property's own key_name. Only call this for a root
     *                        property, a nested property's key_name isn't guaranteed unique
     *                        and could collide with an unrelated root variable
     *
     * @return int Number of stored values left in place because of a legacy Data Field, 0
     *             if there was no conflict and the values were deleted as usual
     */
    public function deleteStoredValues(string $varname): int
    {
        if ($this->hasLegacyDatafield($varname)) {
            return $this->countStoredValues($varname);
        }

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $this->db->delete("icinga_{$object}_var", Filter::where('varname', $varname));
        }

        return 0;
    }

    /**
     * Detach every stored value for a root property outright, across every object type.
     *
     * @param string $varname the root property's own key_name. Only call this for a root
     *                        property, a nested property's key_name isn't guaranteed unique
     *                        and could collide with an unrelated root variable
     *
     * @return void
     */
    public function detachStoredValues(string $varname): void
    {
        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $this->db->update(
                "icinga_{$object}_var",
                ['property_uuid' => null],
                Filter::where('varname', $varname)
            );
        }
    }

    /**
     * Stamp every stored value still missing a property_uuid with the given one
     *
     * Migrated values had no UUID before, so a later detach would miss them and
     * leave them behind. This makes sure detach finds them.
     *
     * @param string $varname the root property's own key_name, not a nested one
     * @param UuidInterface $propertyUuid
     *
     * @return void
     */
    public function backfillPropertyUuid(string $varname, UuidInterface $propertyUuid): void
    {
        $propertyUuidExpr = DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $this->db->getDbAdapter());

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $this->db->update(
                "icinga_{$object}_var",
                ['property_uuid' => $propertyUuidExpr],
                Filter::matchAll(
                    Filter::where('varname', $varname),
                    Filter::fromQueryString('property_uuid IS NULL')
                )
            );
        }
    }

    /**
     * Rename every stored value for a root property, across every object type
     *
     * Skips the rename if a legacy Data Field owns the old or the new varname. Under the
     * old name its values might be the field's, not this property's. Under the new name it
     * would collide with the field's own row (host_id/varname is a primary key here).
     *
     * @param string $oldVarname the root property's varname before the rename
     * @param string $newVarname the root property's varname after the rename
     *
     * @return int Number of stored values left under the old varname because of a conflict,
     *             0 if there was no conflict and the values were renamed as usual
     */
    public function renameStoredValues(string $oldVarname, string $newVarname): int
    {
        if ($this->wouldRenameCollideWithLegacyDatafield($oldVarname, $newVarname)) {
            // A new-name conflict can block this even with nothing stored under the old
            // name yet, so 0 can't mean "no conflict" here.
            return max(1, $this->countStoredValues($oldVarname));
        }

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $this->db->update(
                "icinga_{$object}_var",
                ['varname' => $newVarname],
                Filter::where('varname', $oldVarname)
            );
        }

        return 0;
    }

    /**
     * Rename a nested property's key everywhere it's stored
     *
     * Ancestors keep their name, only the last step of the path changes. Still
     * checked against a legacy Data Field first, since this writes into the
     * same stored data that field might own.
     *
     * @param array  $property property being renamed, needs at least 'key_name' (the old name)
     * @param array  $parent immediate parent row, used to walk up to the root
     * @param string $newKeyName the property's new key_name
     *
     * @return int Values left alone because of a legacy Data Field, 0 if renamed as usual
     */
    public function renameNestedStoredValues(array $property, array $parent, string $newKeyName): int
    {
        [$rootProp, $oldPath] = $this->resolveRootProperty($property, $parent);

        if ($this->hasLegacyDatafield($rootProp['key_name'])) {
            return $this->countStoredValues($rootProp['key_name']);
        }

        $newPath = $oldPath;
        $newPath[array_key_last($newPath)] = $newKeyName;

        $db = $this->db->getDbAdapter();
        $rootType = $rootProp['value_type'];

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $objectType) {
            $idColumn = "{$objectType}_id";
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
                $varValue = json_decode($varRow['varvalue'] ?? '', true);

                if ($rootType !== 'dynamic-dictionary') {
                    $this->renameDictionaryItem($varValue, $oldPath, $newPath);
                } else {
                    $this->renameDictionaryItemInEveryEntry($varValue, $oldPath, $newPath);
                }

                $object = $objectClass::loadWithAutoIncId($varRow[$idColumn], $this->db);
                $vars = $object->vars();
                $vars->set($varRow['varname'], $varValue);
                $vars->storeToDb($object);
            }
        }

        return 0;
    }

    /**
     * Whether renaming from $oldVarname to $newVarname would collide with a legacy Data Field
     *
     * Public so a form can validate this before submit instead of only finding out after.
     *
     * @return bool
     */
    public function wouldRenameCollideWithLegacyDatafield(string $oldVarname, string $newVarname): bool
    {
        return $this->hasLegacyDatafield($oldVarname) || $this->hasLegacyDatafield($newVarname);
    }

    /**
     * Whether deleting this varname or changing its type would collide with a legacy Data Field
     *
     * Public so a form can validate this before submit instead of only finding out after.
     *
     * @return bool
     */
    public function wouldDeleteCollideWithLegacyDatafield(string $varname): bool
    {
        return $this->hasLegacyDatafield($varname);
    }

    /**
     * Whether a legacy Data Field still exists under this exact varname
     *
     * @return bool
     */
    private function hasLegacyDatafield(string $varname): bool
    {
        $db = $this->db->getDbAdapter();

        return (bool) $db->fetchOne(
            $db->select()
               ->from('director_datafield', ['cnt' => 'COUNT(*)'])
               ->where('varname = ?', $varname)
        );
    }

    /**
     * Whether creating or renaming a legacy Data Field to $varname would collide with a
     * root property
     *
     * Public so a form can validate this before submit instead of only finding out after.
     *
     * @return bool
     */
    public function wouldDatafieldCollideWithProperty(string $varname): bool
    {
        return $this->hasProperty($varname);
    }

    /**
     * Whether a root property still exists under this exact key_name
     *
     * @return bool
     */
    private function hasProperty(string $varname): bool
    {
        $db = $this->db->getDbAdapter();

        return (bool) $db->fetchOne(
            $db->select()
               ->from('director_property', ['cnt' => 'COUNT(*)'])
               ->where('parent_uuid IS NULL')
               ->where('key_name = ?', $varname)
        );
    }

    /**
     * Count how many stored values exist under this varname, across every object type
     *
     * @return int
     */
    private function countStoredValues(string $varname): int
    {
        $db = $this->db->getDbAdapter();
        $total = 0;

        foreach (['host', 'service', 'notification', 'command', 'user', 'service_set'] as $object) {
            $total += (int) $db->fetchOne(
                $db->select()
                   ->from("icinga_{$object}_var", ['cnt' => 'COUNT(*)'])
                   ->where('varname = ?', $varname)
            );
        }

        return $total;
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
