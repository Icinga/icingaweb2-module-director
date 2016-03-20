<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Util;
use Icinga\Authentication\Auth;
use Icinga\Application\Icinga;

class DirectorActivityLog extends DbObject
{
    protected $table = 'director_activity_log';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
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
    );

    protected static function username()
    {
        if (Icinga::app()->isCli()) {
            return 'cli';
        }

        $auth = Auth::getInstance();
        if ($auth->isAuthenticated()) {
            return $auth->getUser()->getUsername();
        } else {
            return '<unknown>';
        }
    }

    public static function logCreation(DbObject $object, Db $db)
    {
        $data = array(
            'object_name'     => $object->object_name,
            'action_name'     => 'create',
            'author'          => self::username(),
            'object_type'     => $object->getTableName(),
            'new_properties'  => $object->toJson(null, true),
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        return self::create($data)->store($db);
    }

    public static function logModification(DbObject $object, Db $db)
    {
        $data = array(
            'object_name'     => $object->object_name,
            'action_name'     => 'modify',
            'author'          => self::username(),
            'object_type'     => $object->getTableName(),
            'old_properties'  => json_encode($object->getPlainUnmodifiedObject()),
            'new_properties'  => $object->toJson(null, true),
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        return self::create($data)->store($db);
    }

    public static function logRemoval(DbObject $object, Db $db)
    {
        $plain = $object->getCachedUnmodifiedObject();
        $data = array(
            'object_name'     => $plain->object_name,
            'action_name'     => 'delete',
            'author'          => self::username(),
            'object_type'     => $object->getTableName(),
            'old_properties'  => json_encode($plain),
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        return self::create($data)->store($db);
    }
}
