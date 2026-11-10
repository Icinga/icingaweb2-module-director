<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;
use stdClass;

/**
 * Cleanup of the values that a property leaves behind once it is no longer reachable
 *
 * A property becomes unreachable when it is detached directly or lost with a
 * source, see {@see PropertySourceChange}.
 */
class PropertyDetachmentCleaner
{
    /** Objects to clean between two garbage collections */
    private const COLLECT_GARBAGE_EVERY = 50;

    /**
     * Wipe the saved value for the given properties wherever it has no other way left to be reached
     *
     * Call this after the attachments of the given properties have been
     * removed from the object. The holders are the object and everything below
     * it. For a command, every host and service is a holder too. A value of
     * the property goes unless the host or service still reaches the property
     * through itself, its templates, its check command or a command that the
     * check command imports. Logged and deleted per holder, since two holders
     * can lose a different subset. Apart from that, the number of queries does
     * not grow with the number of holders, except for a few queries per
     * thousand holders.
     *
     * @param IcingaObject $object              The object losing these properties
     * @param string[]     $quotedPropertyUuids These uuids, already quoted for use in a query
     * @param Db           $db                  Database connection
     *
     * @return void
     */
    public static function removeStaleValues(IcingaObject $object, array $quotedPropertyUuids, Db $db): void
    {
        if (empty($quotedPropertyUuids)) {
            return;
        }

        $type = $object->getShortTableName();
        $objectId = (int) $object->get('id');
        $sources = new CustomPropertySources($db);
        $holderTypes = $type === 'command' ? ['command', 'host', 'service'] : [$type];

        foreach ($holderTypes as $holderType) {
            $holderIds = $holderType === $type
                ? array_merge([$objectId], $sources->listDescendantIds($type, $objectId))
                : null;
            $rowsByHolderId = static::fetchLinkedValues($holderType, $quotedPropertyUuids, $db, $holderIds);
            if (empty($rowsByHolderId)) {
                continue;
            }

            $reachable = $sources->getReachablePropertyUuids($holderType, array_keys($rowsByHolderId));
            $orphanedRowsByHolderId = [];
            foreach ($rowsByHolderId as $holderId => $rows) {
                foreach ($rows as $hex => $row) {
                    if (($reachable[$holderId][$row['varname']] ?? null) !== $row['uuid']) {
                        $orphanedRowsByHolderId[$holderId][$hex] = $row;
                    }
                }
            }

            static::removeValues($holderType, $orphanedRowsByHolderId, $object, $db);
        }
    }

    /**
     * Delete the given values, logging one modification per holder
     *
     * @param string                                                          $type           Short table name of
     *   the holders, e.g. "host"
     * @param array<int, array<string, array{uuid: string, varname: string}>> $rowsByHolderId Values to delete per
     *   holder id
     * @param IcingaObject                                                    $object         The object whose
     *   change causes the removal, reused when it is one of the holders
     * @param Db                                                              $db             Database connection
     *
     * @return void
     */
    public static function removeValues(string $type, array $rowsByHolderId, IcingaObject $object, Db $db): void
    {
        $dbAdapter = $db->getDbAdapter();
        $objectId = $object->getShortTableName() === $type ? (int) $object->get('id') : null;
        $class = DbObjectTypeRegistry::classByType($type);

        $cleanedCount = 0;
        foreach ($rowsByHolderId as $holderId => $rows) {
            if (empty($rows)) {
                continue;
            }

            $holder = $holderId === $objectId
                ? $object
                : $class::loadWithAutoIncId($holderId, $object->getConnection());

            $oldVars = self::plainVars($holder->vars());
            $newVars = clone $oldVars;
            foreach ($rows as $row) {
                unset($newVars->{$row['varname']});
            }

            // may log fewer vars than the batch if some survived, expected
            DirectorActivityLog::logCustomVariableModification($holder, $oldVars, $newVars, $db);

            $dbAdapter->delete(
                'icinga_' . $type . '_var',
                $dbAdapter->quoteInto(
                    'property_uuid IN (?)',
                    DbUtil::quoteBinaryCompat(array_column($rows, 'uuid'), $dbAdapter)
                )
                . ' AND '
                . $dbAdapter->quoteInto($type . '_id = ?', $holderId)
            );

            // Each object cleaned leaves unused data behind, free it or a big template runs out of memory
            $cleanedCount++;
            if ($cleanedCount % self::COLLECT_GARBAGE_EVERY === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Fetch the values linked to the given properties, per holder
     *
     * @param string   $type                Short table name of the holders, e.g. "host"
     * @param string[] $quotedPropertyUuids
     * @param Db       $db
     * @param ?int[]   $holderIds           Only the values of these holders, null for the values of every holder
     *
     * @return array<int, array<string, array{uuid: string, varname: string}>> Rows keyed by hex property uuid
     */
    protected static function fetchLinkedValues(
        string $type,
        array $quotedPropertyUuids,
        Db $db,
        ?array $holderIds = null
    ): array {
        $dbAdapter = $db->getDbAdapter();
        $query = $dbAdapter->select()
            ->from('icinga_' . $type . '_var', [
                'holder'        => $type . '_id',
                'varname'       => 'varname',
                'property_uuid' => 'property_uuid',
            ])
            ->where('property_uuid IN (?)', $quotedPropertyUuids);

        $queries = [];
        if ($holderIds === null) {
            $queries[] = $query;
        } else {
            foreach (array_chunk($holderIds, 1000) as $chunk) {
                $queries[] = (clone $query)->where($type . '_id IN (?)', $chunk);
            }
        }

        $result = [];
        foreach ($queries as $chunkQuery) {
            foreach ($dbAdapter->fetchAll($chunkQuery, [], PDO::FETCH_ASSOC) as $row) {
                $row = DbUtil::normalizeRow($row);
                $result[(int) $row['holder']][bin2hex($row['property_uuid'])] = [
                    'uuid'    => $row['property_uuid'],
                    'varname' => $row['varname'],
                ];
            }
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
