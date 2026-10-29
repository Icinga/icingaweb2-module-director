<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Authentication\Auth;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;
use stdClass;

class DirectorActivityLog extends DbObject
{
    public const ACTION_CREATE = 'create';
    public const ACTION_DELETE = 'delete';
    public const ACTION_MODIFY = 'modify';

    /** @deprecated */
    public const AUDIT_REMOVE = 'remove';

    protected $table = 'director_activity_log';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'              => null,
        'object_name'     => null,
        'action_name'     => null,
        'object_type'     => null,
        'old_properties'  => null,
        'new_properties'  => null,
        'author'          => null,
        'change_time'     => null,
        'checksum'        => null,
        'parent_checksum' => null,
    ];

    protected $binaryProperties = [
        'checksum',
        'parent_checksum'
    ];

    /** @var ?string */
    protected static $overriddenUsername = null;

    /** @var ?IcingaObject The object a pending note belongs to */
    protected static $noteObject = null;

    /** @var ?string */
    protected static $pendingNote = null;

    /**
     * @param $name
     *
     * @codingStandardsIgnoreStart
     *
     * @return self
     */
    protected function setObject_Name($name)
    {
        // @codingStandardsIgnoreEnd

        if ($name === null) {
            $name = '';
        }

        return $this->reallySet('object_name', $name);
    }

    public static function username()
    {
        if (self::$overriddenUsername) {
            return self::$overriddenUsername;
        }

        if (Icinga::app()->isCli()) {
            return 'cli';
        }

        $auth = Auth::getInstance();
        if ($auth->isAuthenticated()) {
            return $auth->getUser()->getUsername();
        } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
            return '<' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '>';
        } elseif (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            return '<' . $_SERVER['REMOTE_ADDR'] . '>';
        } else {
            return '<unknown>';
        }
    }

    protected static function ip()
    {
        if (Icinga::app()->isCli()) {
            return 'cli';
        }

        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return '0.0.0.0';
        }
    }

    /**
     * @param Db $connection
     * @return DirectorActivityLog
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function loadLatest(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from('director_activity_log', ['id' => 'MAX(id)']);

        return static::load($db->fetchOne($query), $connection);
    }

    public static function logCreation(IcingaObject $object, Db $db)
    {
        // TODO: extend this to support non-IcingaObjects and multikey objects
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $newProps = $object->toJson(null, true);

        $data = [
            'object_name'     => $name,
            'action_name'     => self::ACTION_CREATE,
            'author'          => static::username(),
            'object_type'     => $type,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum()
        ];

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);

        static::audit($db, [
            'action'      => self::ACTION_CREATE,
            'object_type' => $type,
            'object_name' => $name,
            'new_props'   => $newProps,
        ]);

        return static::storeEntry($data, $object, $db);
    }

    public static function logModification(IcingaObject $object, Db $db)
    {
        $oldProps = json_encode($object->getPlainUnmodifiedObject());
        $newProps = $object->toJson(null, true);

        return self::logModificationChange(
            $object,
            $object->getObjectName(),
            $object->getTableName(),
            $oldProps,
            $newProps,
            $db
        );
    }

    /**
     * Log a vars-only modification, for a REST write that only touches custom
     * variables and never runs through the usual object save flow that logs itself
     *
     * @param IcingaObject $object
     * @param stdClass $oldVars
     * @param stdClass $newVars
     * @param Db $db
     *
     * @return bool
     */
    public static function logCustomVariableModification(
        IcingaObject $object,
        stdClass $oldVars,
        stdClass $newVars,
        Db $db
    ) {
        // Log the whole object like any other change, restoring from a
        // vars-only entry would blank every other property on the object
        $oldPlain = $object->toPlainObject(false, true);
        $newPlain = clone $oldPlain;
        $oldPlain->vars = $oldVars;
        $newPlain->vars = $newVars;
        $oldProps = json_encode($oldPlain);
        $newProps = json_encode($newPlain);

        return self::logModificationChange(
            $object,
            $object->getObjectName(),
            $object->getTableName(),
            $oldProps,
            $newProps,
            $db
        );
    }

    private static function logModificationChange(
        IcingaObject $object,
        string $name,
        string $type,
        string $oldProps,
        string $newProps,
        Db $db
    ) {
        if ($oldProps === $newProps) {
            // Nothing actually changed, skip logging a fake modification.
            return false;
        }

        $data = [
            'object_name'     => $name,
            'action_name'     => self::ACTION_MODIFY,
            'author'          => static::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum()
        ];

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);

        static::audit($db, [
            'action'      => self::ACTION_MODIFY,
            'object_type' => $type,
            'object_name' => $name,
            'old_props'   => $oldProps,
            'new_props'   => $newProps,
        ]);

        return static::storeEntry($data, $object, $db);
    }

    public static function logRemoval(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        /** @var stdClass $plainUnmodifiedObject */
        $plainUnmodifiedObject = $object->getPlainUnmodifiedObject();

        if ($object instanceof IcingaServiceSet) {
            $services = [];
            foreach ($object->getCachedServices() as $service) {
                $services[$service->getObjectName()] = $service->toPlainObject();
            }

            $plainUnmodifiedObject->services = $services;
        }

        $oldProps = json_encode($plainUnmodifiedObject);

        $data = [
            'object_name'     => $name,
            'action_name'     => self::ACTION_DELETE,
            'author'          => static::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum()
        ];

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);

        static::audit($db, [
            'action'      => self::AUDIT_REMOVE,
            'object_type' => $type,
            'object_name' => $name,
            'old_props'   => $oldProps
        ]);

        return static::storeEntry($data, $object, $db);
    }

    public static function audit(Db $db, $properties)
    {
        if ($db->settings()->get('enable_audit_log') !== 'y') {
            return;
        }

        $log = [];
        $properties = array_merge([
            'username' => static::username(),
            'address'  => static::ip(),
        ], $properties);

        foreach ($properties as $key => $val) {
            $log[] = "$key=" . json_encode($val);
        }

        Logger::info('(director) ' . implode(' ', $log));
    }

    /**
     * Save this note on the entry logged for the object, instead of the
     * entries of other objects stored along with it
     *
     * @param IcingaObject $object
     * @param string $note
     */
    public static function setPendingNote(IcingaObject $object, string $note): void
    {
        self::$noteObject = $object;
        self::$pendingNote = $note;
    }

    public static function clearPendingNote(): void
    {
        self::$noteObject = null;
        self::$pendingNote = null;
    }

    private static function storeEntry(array $data, IcingaObject $object, Db $db)
    {
        $entry = static::create($data);
        $stored = $entry->store($db);

        if ($stored && self::$pendingNote !== null && self::$noteObject === $object) {
            $db->storeActivityLogNotes([(int) $entry->get('id')], self::$pendingNote);
        }

        return $stored;
    }

    public static function overrideUsername($username)
    {
        self::$overriddenUsername = $username;
    }

    public static function restoreUsername()
    {
        self::$overriddenUsername = null;
    }
}
