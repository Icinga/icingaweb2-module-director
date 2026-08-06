<?php

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
 * Cleans up custom variable values left behind once a property is no
 * longer reachable, either detached directly or lost with its template
 *
 * A value can stay saved on an object even after it stops being able to
 * inherit it. This used to leave that value stuck there with no way to
 * see or edit it through the usual UI.
 */
class PropertyDetachmentCleaner
{
    /**
     * Wipe the saved value for the given properties, on this object and
     * on every object underneath it that stored its own copy
     *
     * Logs an activity entry for each object that loses a value, so
     * there's a trace of why it disappeared.
     *
     * @param string[] $quotedPropertyUuids Already quoted with DbUtil::quoteBinaryCompat()
     */
    public static function removeStaleValues(IcingaObject $object, array $quotedPropertyUuids, Db $db): void
    {
        if (empty($quotedPropertyUuids)) {
            return;
        }

        $type = $object->getShortTableName();
        $dbAdapter = $db->getDbAdapter();
        $objectId = (int) $object->get('id');
        $class = DbObjectTypeRegistry::classByType($type);

        $rows = $dbAdapter->fetchAll(
            $dbAdapter->select()
                ->from('icinga_' . $type . '_var')
                ->where('property_uuid IN (?)', $quotedPropertyUuids)
        );

        $removedNamesByHolderId = [];
        foreach ($rows as $row) {
            $holderId = (int) $row->{$type . '_id'};
            $removedNamesByHolderId[$holderId][] = $row->varname;
        }

        $objectsToCleanUp = [$objectId];

        if (! empty($removedNamesByHolderId)) {
            // the cached tree might predate the inheritance change we're
            // checking against, so force a fresh read
            IcingaTemplateRepository::clear();
            // check inheritance directly, no need to load a full object
            // just to find out it isn't even a descendant
            $tree = IcingaTemplateRepository::instanceByType($type, $db)->tree();

            foreach ($removedNamesByHolderId as $holderId => $removedNames) {
                $isHolderTheObjectItself = $holderId === $objectId;
                if (! $isHolderTheObjectItself && ! array_key_exists($objectId, $tree->getAncestorsById($holderId))) {
                    continue;
                }

                if ($isHolderTheObjectItself) {
                    $holder = $object;
                } else {
                    $holder = $class::loadWithAutoIncId($holderId, $object->getConnection());
                    $objectsToCleanUp[] = $holderId;
                }

                $oldVars = self::plainVars($holder->vars());
                $newVars = clone $oldVars;
                foreach ($removedNames as $removedName) {
                    unset($newVars->$removedName);
                }

                DirectorActivityLog::logCustomVariableModification($holder, $oldVars, $newVars, $db);
            }
        }

        $propertyWhere = $dbAdapter->quoteInto('property_uuid IN (?)', $quotedPropertyUuids);
        $objectsWhere = $dbAdapter->quoteInto($type . '_id IN (?)', $objectsToCleanUp);
        $dbAdapter->delete('icinga_' . $type . '_var', $propertyWhere . ' AND ' . $objectsWhere);
    }

    /**
     * Wipe values that only existed because of a template import which
     * just got removed
     *
     * @param string[] $removedImportNames Imports this object lost, compared to what's in the database
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
     * Own values that would lose their backing property if the object
     * ended up with the given imports instead
     *
     * Only for warning the user before save, doesn't touch the database.
     * Takes the pending imports as an argument instead of reading them
     * off the object, since a form calls this before applying anything.
     *
     * @param string[] $pendingImportNames
     *
     * @return string[]
     */
    public static function previewValueNamesAtRiskIfImportsBecome(IcingaObject $object, array $pendingImportNames): array
    {
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

        return $dbAdapter->fetchCol(
            $dbAdapter->select()
                ->from('icinga_' . $type . '_var', ['varname'])
                ->where($type . '_id = ?', (int) $object->get('id'))
                ->where('property_uuid IN (?)', DbUtil::quoteBinaryCompat(array_values($orphanedUuids), $dbAdapter))
        );
    }

    /**
     * Properties only reachable through the lost ids, and not through the
     * object itself or any of its new ancestors
     *
     * @param int[] $lostAncestorIds
     * @param int[] $newAncestorIds
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
     * @param string[] $importNames
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
     * @param int[] $ids
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
