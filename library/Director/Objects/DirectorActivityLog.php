<?php

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

        return static::create($data)->store($db);
    }

    public static function logModification(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $oldProps = json_encode($object->getPlainUnmodifiedObject());
        $newProps = $object->toJson(null, true);

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

        return static::create($data)->store($db);
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

        return static::create($data)->store($db);
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

    public static function overrideUsername($username)
    {
        self::$overriddenUsername = $username;
    }

    public static function restoreUsername()
    {
        self::$overriddenUsername = null;
    }
}
