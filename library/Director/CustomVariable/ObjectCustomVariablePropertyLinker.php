<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Ramsey\Uuid\Uuid;

/**
 * Stamps a property uuid on a synced value when the object reaches a
 * property of that name
 *
 * A sync just sets a value by name, it has no idea properties even exist.
 * This checks, for every synced object, which properties it reaches through
 * itself, its templates and, for a host or service, its check command and the
 * commands that command imports. A value is linked to the property of its
 * name, and unlinked when the object no longer reaches one, the same way basket
 * restore already does for its own objects.
 *
 * Every value of every synced object is checked in each run, because a
 * change of the object's imports or check command, of a template, or of a
 * command can change what the object reaches between two runs. One
 * {@see CustomPropertySources} instance serves the whole run, so every
 * template and command is looked up once.
 */
class ObjectCustomVariablePropertyLinker
{
    /**
     * Links every synced object's values to the property of the same name it
     * reaches, and unlinks the values it reaches no property for
     *
     * @param IcingaObject[] $objects the objects a sync run just prepared
     * @param Db $db the target database this sync run is writing to
     *
     * @return void
     */
    public static function linkSyncedObjects(array $objects, Db $db): void
    {
        $sources = new CustomPropertySources($db);
        foreach ($objects as $object) {
            if (! $object instanceof IcingaObject || ! $object->supportsCustomProperties()) {
                continue;
            }

            $vars = $object->vars();
            $keys = $vars->listKeys();
            if (empty($keys)) {
                continue;
            }

            $reachable = $sources->getReachableProperties($object);
            foreach ($keys as $key) {
                $currentUuid = $vars->get($key)?->getUuid();
                if (! isset($reachable[$key])) {
                    if ($currentUuid !== null) {
                        $vars->clearVarUuid($key);
                    }

                    continue;
                }

                $uuid = Uuid::fromBytes($reachable[$key]['uuid']);
                if ($currentUuid === null || ! $currentUuid->equals($uuid)) {
                    $vars->registerVarUuid($key, $uuid);
                }
            }
        }
    }
}
