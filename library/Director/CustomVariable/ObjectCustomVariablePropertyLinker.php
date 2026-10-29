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
 * Links synced values to a property their object can reach through an
 * imported template or the check command
 *
 * A sync sets values by name only. Already linked values are skipped, unless
 * the sync changed the imports or the check command.
 *
 * @phpstan-import-type CommandProperty from ObjectCustomPropertySources
 */
class ObjectCustomVariablePropertyLinker
{
    /**
     * Links every synced object's values to a property, wherever one is
     * already attached on the object, its templates or its check command
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

            // Switching the check command changes which variables the object can reach, like a new import.
            // Sync sets it by name, so resolve it first or it is not marked as modified yet.
            $object->resolveUnresolvedRelatedProperties();
            $sourcesChanged = $object->imports()->hasBeenModified()
                || in_array('check_command_id', $object->listModifiedProperties(), true);
            $keys = $sourcesChanged ? $object->vars()->listKeys() : $object->vars()->listKeysWithoutUuid();
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
     * A variable no template settles falls back to the properties of the check command.
     *
     * @param string $type the object type's short name, host, service and so on
     * @param array<int, array{0: IcingaObject, 1: string[]}> $entries one entry per object,
     *        paired with the keys that need a reachability check
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

        // Find which of these variables any command has, with one query for the whole batch.
        // Most synced variables belong to no command, so most objects can skip the command lookup.
        $commandKeys = array_flip($dba->fetchCol(
            $dba->select()
                ->from(['dp' => 'director_property'], ['key_name'])
                ->join(['icp' => 'icinga_command_property'], 'dp.uuid = icp.property_uuid', [])
                ->where('dp.key_name IN (?)', array_values($allKeys))
                ->where('dp.parent_uuid IS NULL')
        ));

        // The properties of a command do not depend on the object, so each command is looked up once
        $commandPropertiesById = [];

        foreach ($entries as [$object, $keys]) {
            $ids = $object->listAncestorIds();
            if ($object->hasBeenLoadedFromDb()) {
                $ids[] = (int) $object->get('id');
            }

            $commandProperties = null;
            foreach ($keys as $key) {
                $uuid = null;
                if (isset($uuidsByKey[$key]) && self::isAttachedToAny($attachedIdsByKey[$key], $ids)) {
                    $uuid = $uuidsByKey[$key];
                } elseif (isset($commandKeys[$key])) {
                    // Looked up once per command, and only when the templates did not settle the key
                    $commandProperties ??= self::commandProperties($object, $db, $commandPropertiesById);
                    $uuid = $commandProperties[$key]['uuid'] ?? null;
                }

                $currentUuid = $object->vars()->get($key)?->getUuid();

                if ($uuid === null) {
                    if ($currentUuid !== null) {
                        $object->vars()->clearVarUuid($key);
                    }

                    continue;
                }

                if ($currentUuid === null || ! $currentUuid->equals(Uuid::fromBytes($uuid))) {
                    $object->vars()->registerVarUuid($key, Uuid::fromBytes($uuid));
                }
            }
        }
    }

    /**
     * Get the properties of the check command the object uses, from the batch cache when known
     *
     * @param IcingaObject $object
     * @param Db $db
     * @param array<int, array<string, CommandProperty>> $cache properties already looked up, keyed by command id
     *
     * @return array<string, CommandProperty>
     */
    private static function commandProperties(IcingaObject $object, Db $db, array &$cache): array
    {
        $commandId = ObjectCustomPropertySources::resolveCommandId($object);
        if ($commandId === null) {
            return [];
        }

        return $cache[$commandId] ??= ObjectCustomPropertySources::commandPropertiesByCommandId($commandId, $db);
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
