<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaObject;
use Ramsey\Uuid\Uuid;
use Zend_Db;

/**
 * Stamps a property uuid on a synced value when a template the object
 * imports already has that property attached
 *
 * A sync just sets a value by name, it has no idea properties even exist.
 * This checks, for every synced object at once, whether that name is
 * already attached somewhere up its own ancestry, and links the value to
 * it if so, the same way basket restore already does for its own objects.
 */
class ObjectCustomVariablePropertyLinker
{
    /**
     * Links every synced object's values to a property, wherever one is
     * already attached somewhere in that object's own ancestry
     *
     * @param IcingaObject[] $objects the objects a sync run just prepared
     * @param Db $db the target database this sync run is writing to
     *
     * @return void
     */
    public static function linkSyncedObjects(array $objects, Db $db): void
    {
        $byType = [];
        foreach ($objects as $object) {
            if (! $object instanceof IcingaObject || ! $object->supportsCustomProperties()) {
                continue;
            }

            $keys = $object->vars()->listKeysWithoutUuid();
            if (empty($keys)) {
                continue;
            }

            $byType[$object->getShortTableName()][] = [$object, $keys];
        }

        foreach ($byType as $type => $entries) {
            self::linkType($type, $entries, $db);
        }
    }

    /**
     * Does the actual linking for one object type, one query for the whole batch
     *
     * @param string $type the object type's short name, host, service and so on
     * @param array<int, array{0: IcingaObject, 1: string[]}> $entries one entry per object,
     *        paired with the keys that object still has no uuid for
     * @param Db $db the target database this sync run is writing to
     *
     * @return void
     */
    private static function linkType(string $type, array $entries, Db $db): void
    {
        $allKeys = [];
        foreach ($entries as [, $keys]) {
            foreach ($keys as $key) {
                $allKeys[$key] = $key;
            }
        }

        $dba = $db->getDbAdapter();
        $rows = $dba->fetchAll(
            $dba->select()
                ->from(['dp' => 'director_property'], ['key_name', 'uuid'])
                ->join(['iop' => "icinga_{$type}_property"], 'dp.uuid = iop.property_uuid', [])
                ->join(['io' => "icinga_{$type}"], "iop.{$type}_uuid = io.uuid", ['attached_id' => 'io.id'])
                ->where('dp.key_name IN (?)', array_values($allKeys))
                ->where('dp.parent_uuid IS NULL'),
            [],
            Zend_Db::FETCH_ASSOC
        );

        // A root property's own name is unique across the whole schema, so the uuid
        // for a name is one fact for the entire batch, only whether it is attached
        // to a given object changes from one object to the next.
        $uuidsByKey = [];
        $attachedIdsByKey = [];
        foreach ($rows as $row) {
            $row = DbUtil::normalizeRow($row);
            $uuidsByKey[$row['key_name']] = $row['uuid'];
            $attachedIdsByKey[$row['key_name']][(int) $row['attached_id']] = true;
        }

        foreach ($entries as [$object, $keys]) {
            $ids = $object->listAncestorIds();
            if ($object->hasBeenLoadedFromDb()) {
                $ids[] = (int) $object->get('id');
            }

            foreach ($keys as $key) {
                if (! isset($uuidsByKey[$key])) {
                    continue;
                }

                if (! self::isAttachedToAny($attachedIdsByKey[$key], $ids)) {
                    continue;
                }

                $object->vars()->registerVarUuid($key, Uuid::fromBytes($uuidsByKey[$key]));
            }
        }
    }

    /**
     * Checks whether any of the given ids show up in the attached set
     *
     * @param array<int, bool> $attachedIds ids the property is attached to, keyed by id
     * @param int[] $ids the ids to check, an object's own id plus its ancestors
     *
     * @return bool
     */
    private static function isAttachedToAny(array $attachedIds, array $ids): bool
    {
        foreach ($ids as $id) {
            if (isset($attachedIds[$id])) {
                return true;
            }
        }

        return false;
    }
}
