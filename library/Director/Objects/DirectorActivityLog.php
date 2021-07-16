<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use Icinga\Authentication\Auth;
use Icinga\Application\Icinga;
use Icinga\Application\Logger;

class DirectorActivityLog extends DbObject
{
    const LIVE_MODIFICATION_VALUE_SCHEDULED = 'scheduled';
    const LIVE_MODIFICATION_VALUE_SUCCEEDED = 'succeeded';
    const LIVE_MODIFICATION_VALUE_FAILED = 'failed';
    const LIVE_MODIFICATION_VALUE_IMPOSSIBLE = 'impossible';
    const LIVE_MODIFICATION_VALUE_DISABLED = 'disabled';

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
        'live_modification' => self::LIVE_MODIFICATION_VALUE_IMPOSSIBLE
    ];

    protected $binaryProperties = [
        'checksum',
        'parent_checksum'
    ];

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

    protected static function username()
    {
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

        $data = array(
            'object_name'     => $name,
            'action_name'     => 'create',
            'author'          => static::username(),
            'object_type'     => $type,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum(),
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);
        if (IcingaObjectLiveModificationAvailability::isEnabled()) {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_IMPOSSIBLE;
        } else {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_DISABLED;
        }


        static::audit($db, array(
            'action'      => 'create',
            'object_type' => $type,
            'object_name' => $name,
            'new_props'   => $newProps,
        ));

        $activityLog = static::create($data);
        $activityLog->store($db);
        return $activityLog;
    }

    public static function logModification(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $oldProps = json_encode($object->getPlainUnmodifiedObject());
        $newProps = $object->toJson(null, true);

        $data = array(
            'object_name'     => $name,
            'action_name'     => 'modify',
            'author'          => static::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'new_properties'  => $newProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);
        if (IcingaObjectLiveModificationAvailability::isEnabled()) {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_IMPOSSIBLE;
        } else {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_DISABLED;
        }

        static::audit($db, array(
            'action'      => 'modify',
            'object_type' => $type,
            'object_name' => $name,
            'old_props'   => $oldProps,
            'new_props'   => $newProps,
        ));

        $activityLog = static::create($data);
        $activityLog->store($db);

        return $activityLog;
    }

    public static function logRemoval(IcingaObject $object, Db $db)
    {
        $name = $object->getObjectName();
        $type = $object->getTableName();
        $oldProps = json_encode($object->getPlainUnmodifiedObject());

        $data = array(
            'object_name'     => $name,
            'action_name'     => 'delete',
            'author'          => static::username(),
            'object_type'     => $type,
            'old_properties'  => $oldProps,
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);
        if (IcingaObjectLiveModificationAvailability::isEnabled()) {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_IMPOSSIBLE;
        } else {
            $data['live_modification'] = self::LIVE_MODIFICATION_VALUE_DISABLED;
        }

        static::audit($db, array(
            'action'      => 'remove',
            'object_type' => $type,
            'object_name' => $name,
            'old_props'   => $oldProps
        ));

        $activityLog = static::create($data);
        $activityLog->store($db);
        return $activityLog;
    }

    public static function audit(Db $db, $properties)
    {
        if ($db->settings()->enable_audit_log !== 'y') {
            return;
        }

        $log = array();
        $properties = array_merge(
            array(
                'username' => static::username(),
                'address'  => static::ip(),
            ),
            $properties
        );

        foreach ($properties as $key => & $val) {
            $log[] = "$key=" . json_encode($val);
        }

        Logger::info('(director) ' . implode(' ', $log));
    }
}
