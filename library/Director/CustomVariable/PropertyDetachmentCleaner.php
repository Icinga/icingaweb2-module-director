<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use PDO;
use stdClass;

/**
 * Cleans up values left behind once a property is no longer reachable,
 * detached directly or lost with its template
 */
class PropertyDetachmentCleaner
{
    /**
     * Wipe the saved value for the given properties, on this object and
     * everything below it with no other way left to reach them
     *
     * A holder may still reach a lost property through another ancestor or
     * its own direct attachment, so each one gets checked first. Logged and
     * deleted per holder, since two holders can lose a different subset.
     *
     * @param IcingaObject $object              The object losing these properties
     * @param string[]     $quotedPropertyUuids These uuids, already quoted for use in a query
     * @param Db           $db                  Database connection
     */
    public static function removeStaleValues(IcingaObject $object, array $quotedPropertyUuids, Db $db): void
    {
        $orphanedRowsByHolderId = self::findHoldersLosingValues($object, $quotedPropertyUuids, $db, true);
        if (empty($orphanedRowsByHolderId)) {
            return;
        }

        $type = $object->getShortTableName();
        $dbAdapter = $db->getDbAdapter();
        $objectId = (int) $object->get('id');
        $class = DbObjectTypeRegistry::classByType($type);

        foreach ($orphanedRowsByHolderId as $holderId => $orphanedRows) {
            $holder = $holderId === $objectId
                ? $object
                : $class::loadWithAutoIncId($holderId, $object->getConnection());

            $oldVars = self::plainVars($holder->vars());
            $newVars = clone $oldVars;
            foreach ($orphanedRows as $orphanedRow) {
                unset($newVars->{$orphanedRow['varname']});
            }

            // may log fewer vars than the batch if some survived, expected
            DirectorActivityLog::logCustomVariableModification($holder, $oldVars, $newVars, $db);

            $orphanedUuids = array_column($orphanedRows, 'uuid');
            $dbAdapter->delete(
                'icinga_' . $type . '_var',
                $dbAdapter->quoteInto('property_uuid IN (?)', DbUtil::quoteBinaryCompat($orphanedUuids, $dbAdapter))
                . ' AND '
                . $dbAdapter->quoteInto($type . '_id = ?', $holderId)
            );
        }
    }

    /**
     * Every holder that would actually lose one of the given properties,
     * mapped to just the rows they'd lose
     *
     * A holder can still reach a lost property through another ancestor or
     * its own direct attachment, so each one gets checked before being
     * included. Shared by the real cleanup and the pre-save preview, so
     * both always agree on who's affected.
     *
     * @param IcingaObject $object              The object losing these properties
     * @param string[]     $quotedPropertyUuids These uuids, already quoted for use in a query
     * @param Db           $db                  Database connection
     * @param bool         $includeObjectItself Whether to also check the object's own row
     * @param int[]        $cutAncestorIds      Ancestor ids to also treat as unreachable, for a
     *                                          pre-save check where the tree doesn't know yet.
     *                                          Can over-warn if a holder also imports one of
     *                                          these directly, fine for a warning-only check
     *
     * @return array<int, array<string, array{uuid: string, varname: string}>>
     */
    private static function findHoldersLosingValues(
        IcingaObject $object,
        array $quotedPropertyUuids,
        Db $db,
        bool $includeObjectItself,
        array $cutAncestorIds = []
    ): array {
        if (empty($quotedPropertyUuids)) {
            return [];
        }

        $type = $object->getShortTableName();
        $dbAdapter = $db->getDbAdapter();
        $objectId = (int) $object->get('id');

        $rows = $dbAdapter->fetchAll(
            $dbAdapter->select()
                ->from('icinga_' . $type . '_var')
                ->where('property_uuid IN (?)', $quotedPropertyUuids),
            [],
            PDO::FETCH_ASSOC
        );

        $removedRowsByHolderId = [];
        foreach ($rows as $row) {
            $row = DbUtil::normalizeRow($row);
            $holderId = (int) $row[$type . '_id'];

            if (! $includeObjectItself && $holderId === $objectId) {
                continue;
            }

            $removedRowsByHolderId[$holderId][bin2hex($row['property_uuid'])] = [
                'uuid'    => $row['property_uuid'],
                'varname' => $row['varname'],
            ];
        }

        if (empty($removedRowsByHolderId)) {
            return [];
        }

        // the cached tree might predate the inheritance change we're
        // checking against, so force a fresh read
        IcingaTemplateRepository::clear();
        $tree = IcingaTemplateRepository::instanceByType($type, $db)->tree();
        $result = [];

        foreach ($removedRowsByHolderId as $holderId => $removedRows) {
            $isHolderTheObjectItself = $holderId === $objectId;
            $holderAncestorIds = $tree->getAncestorsById($holderId);

            if (! $isHolderTheObjectItself && ! array_key_exists($objectId, $holderAncestorIds)) {
                // not a descendant of $object, unrelated to this detachment
                continue;
            }

            // excludes $object (its attachment row may not be up to date
            // yet) and, pre-save, the ancestors about to be cut off, since
            // the tree here still shows the soon to be dropped import
            // one query per holder, revisit if that shows up slow at scale
            $reachableIds = array_values(array_diff(
                array_merge(array_keys($holderAncestorIds), [$holderId]),
                array_merge([$objectId], $cutAncestorIds)
            ));
            $stillReachableUuids = self::propertyUuidsAttachedToIds($reachableIds, $type, $dbAdapter);
            $orphanedRows = array_diff_key($removedRows, $stillReachableUuids);

            if (! empty($orphanedRows)) {
                $result[$holderId] = $orphanedRows;
            }
        }

        return $result;
    }

    /**
     * Wipe values that only existed because of a template import which
     * just got removed
     *
     * @param IcingaObject $object             The object whose imports changed
     * @param string[]     $removedImportNames Imports this object lost, compared to what's in the database
     * @param Db           $db                 Database connection
     */
    public static function removeValuesLostToRemovedImports(
        IcingaObject $object,
        array $removedImportNames,
        Db $db
    ): void {
        if (empty($removedImportNames)) {
            return;
        }

        $lostAncestorIds = self::ancestorIdsForImportNames($object, $removedImportNames);
        if (empty($lostAncestorIds)) {
            return;
        }

        // the cached tree still shows the old inheritance, force a fresh
        // read now that the new imports are saved
        IcingaTemplateRepository::clear();
        $newAncestorIds = $object->listAncestorIds();

        $dbAdapter = $db->getDbAdapter();
        $orphanedUuids = self::findOrphanedPropertyUuids($object, $lostAncestorIds, $newAncestorIds, $dbAdapter);
        if (empty($orphanedUuids)) {
            return;
        }

        self::removeStaleValues($object, DbUtil::quoteBinaryCompat(array_values($orphanedUuids), $dbAdapter), $db);
    }

    /**
     * Values that would lose their backing property if the object ended up
     * with the given imports instead, on the object itself or a descendant
     *
     * Only for warning the user before save, doesn't touch the database.
     * Takes the pending imports as an argument instead of reading them
     * off the object, since a form calls this before applying anything.
     *
     * @param IcingaObject $object             The object to check
     * @param string[]     $pendingImportNames
     *
     * @return string[]
     */
    public static function previewCustomVarsLostIfImportsRemoved(
        IcingaObject $object,
        array $pendingImportNames
    ): array {
        if (! $object->hasBeenLoadedFromDb()) {
            return [];
        }

        $removedImportNames = array_diff($object->imports()->listOriginalImportNames(), $pendingImportNames);
        $lostAncestorIds = self::ancestorIdsForImportNames($object, $removedImportNames);
        if (empty($lostAncestorIds)) {
            return [];
        }

        $newAncestorIds = self::ancestorIdsForImportNames($object, $pendingImportNames);

        $db = $object->getConnection();
        $dbAdapter = $db->getDbAdapter();
        $orphanedUuids = self::findOrphanedPropertyUuids($object, $lostAncestorIds, $newAncestorIds, $dbAdapter);
        if (empty($orphanedUuids)) {
            return [];
        }

        $type = $object->getShortTableName();
        $quotedUuids = DbUtil::quoteBinaryCompat(array_values($orphanedUuids), $dbAdapter);

        // the object's own ancestors haven't been saved yet, so its own row
        // has to be checked here against the pending state, not below
        $varnames = $dbAdapter->fetchCol(
            $dbAdapter->select()
                ->from('icinga_' . $type . '_var', ['varname'])
                ->where($type . '_id = ?', (int) $object->get('id'))
                ->where('property_uuid IN (?)', $quotedUuids)
        );

        foreach (self::findHoldersLosingValues($object, $quotedUuids, $db, false, $lostAncestorIds) as $orphanedRows) {
            foreach ($orphanedRows as $orphanedRow) {
                $varnames[] = $orphanedRow['varname'];
            }
        }

        return array_values(array_unique($varnames));
    }

    /**
     * Properties only reachable through the lost ids, and not through the
     * object itself or any of its new ancestors
     *
     * @param IcingaObject $object          The object to check
     * @param int[]        $lostAncestorIds
     * @param int[]        $newAncestorIds
     * @param mixed        $dbAdapter       The database adapter to run the lookup with
     *
     * @return string[] Raw binary property uuids, keyed by hex
     */
    private static function findOrphanedPropertyUuids(
        IcingaObject $object,
        array $lostAncestorIds,
        array $newAncestorIds,
        $dbAdapter
    ): array {
        $type = $object->getShortTableName();
        $candidateUuids = self::propertyUuidsAttachedToIds($lostAncestorIds, $type, $dbAdapter);
        if (empty($candidateUuids)) {
            return [];
        }

        $stillReachableIds = $newAncestorIds;
        $stillReachableIds[] = (int) $object->get('id');
        $stillReachableUuids = self::propertyUuidsAttachedToIds($stillReachableIds, $type, $dbAdapter);

        return array_diff_key($candidateUuids, $stillReachableUuids);
    }

    /**
     * Ancestor ids reachable through the given import names, loaded fresh
     * from the database
     *
     * @param IcingaObject $object      The object to check
     * @param string[]     $importNames
     *
     * @return int[]
     */
    private static function ancestorIdsForImportNames(IcingaObject $object, array $importNames): array
    {
        if (empty($importNames)) {
            return [];
        }

        $class = get_class($object);
        $connection = $object->getConnection();
        $usesCompositeKey = is_array($object->getKeyName());
        $ids = [];

        foreach ($importNames as $name) {
            try {
                $import = $usesCompositeKey
                    ? $class::load(['object_name' => $name, 'object_type' => 'template'], $connection)
                    : $class::load($name, $connection);
            } catch (NotFoundError $e) {
                // a name that no longer resolves can't provide anything anymore either
                continue;
            }

            $importId = (int) $import->get('id');
            $ids[$importId] = $importId;

            foreach ($import->listAncestorIds() as $ancestorId) {
                $ids[$ancestorId] = $ancestorId;
            }
        }

        return array_values($ids);
    }

    /**
     * Raw binary property uuids attached to any of the given object ids
     *
     * Keyed by hex so a uuid attached to more than one of these ids only
     * shows up once.
     *
     * @param int[]  $ids
     * @param string $type      Short table name, e.g. "host"
     * @param mixed  $dbAdapter The database adapter to run the lookup with
     *
     * @return string[]
     */
    private static function propertyUuidsAttachedToIds(array $ids, string $type, $dbAdapter): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $dbAdapter->select()
            ->from(['iop' => 'icinga_' . $type . '_property'], ['property_uuid' => 'iop.property_uuid'])
            ->join(['io' => 'icinga_' . $type], "io.uuid = iop.{$type}_uuid", [])
            ->where('io.id IN (?)', $ids);

        $result = [];
        foreach ($dbAdapter->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $row = DbUtil::normalizeRow($row);
            $result[bin2hex($row['property_uuid'])] = $row['property_uuid'];
        }

        return $result;
    }

    /**
     * Turn a set of custom variables into a plain key/value object, the
     * same shape the activity log expects for its before/after snapshots
     *
     * @param iterable $vars The vars to flatten
     *
     * @return stdClass
     */
    private static function plainVars(iterable $vars): stdClass
    {
        $plain = [];
        foreach ($vars as $key => $var) {
            if ($var->hasBeenDeleted()) {
                continue;
            }

            $plain[$key] = $var->getValue();
        }

        ksort($plain);

        return (object) $plain;
    }
}
