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

    protected static function prepareNewObjectProperties(DbObject $object)
    {
        $props = $object->getProperties();
        if ($object->supportsCustomVars()) {
            // $props->vars = $object->vars()->toJson();
        }
        if ($object->supportsGroups()) {
            $props['groups'] = $object->groups()->listGroupNames();
        }
        if ($object->supportsCustomVars()) {
            $props['vars'] = $object->getVars();
        }
        if ($object->supportsImports()) {
            $props['imports'] = $object->imports()->listImportNames();
        }

        return json_encode($props);
    }

    protected static function prepareModifiedProperties(DbObject $object)
    {
        $props = $object->getModifiedProperties();
        if ($object->supportsCustomVars()) {
            $mod = array();
            foreach ($object->vars() as $name => $var) {
                if ($var->hasBeenModified()) {
                    $mod[$name] = $var->getValue();
                }
            }
            if (! empty($mod)) {
                $props['vars'] = (object) $mod;
            }
        }
        if ($object->supportsGroups()) {
            $old = $object->groups()->listOriginalGroupNames();
            $new = $object->groups()->listGroupNames();
            if ($old !== $new) {
                $props['groups'] = $new;
            }
        }
        if ($object->supportsImports()) {
            $old = $object->imports()->listOriginalImportNames();
            $new = $object->imports()->listImportNames();
            if ($old !== $new) {
                $props['imports'] = $new;
            }
        }


        return json_encode($props);
    }

    protected static function prepareOriginalProperties(DbObject $object)
    {
        $props = $object->getOriginalProperties();
        if ($object->supportsCustomVars()) {
            $props['vars'] = (object) array();
            foreach ($object->vars()->getOriginalVars() as $name => $var) {
                $props['vars']->$name = $var->getValue();
            }
        }
        if ($object->supportsGroups()) {
            $groups = $object->groups()->listOriginalGroupNames();
            if (! empty($groups)) {
                $props['groups'] = $groups;
            }
        }
        if ($object->supportsImports()) {
            $imports = $object->imports()->listOriginalImportNames();
            if (! empty($imports)) {
                $props['imports'] = $imports;
            }
        }

        return json_encode($props);
    }

    public static function logCreation(DbObject $object, Db $db)
    {
        $data = array(
            'object_name'     => $object->object_name,
            'action_name'     => 'create',
            'author'          => self::username(),
            'object_type'     => $object->getTableName(),
            'new_properties'  => self::prepareNewObjectProperties($object),
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
            'old_properties'  => self::prepareOriginalProperties($object),
            'new_properties'  => self::prepareModifiedProperties($object),
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        return self::create($data)->store($db);
    }

    public static function logRemoval(DbObject $object, Db $db)
    {
        $data = array(
            'object_name'     => $object->object_name,
            'action_name'     => 'delete',
            'author'          => self::username(),
            'object_type'     => $object->getTableName(),
            'old_properties'  => json_encode($object->getOriginalProperties()),
            'change_time'     => date('Y-m-d H:i:s'), // TODO -> postgres!
            'parent_checksum' => $db->getLastActivityChecksum()
        );

        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = Util::hex2binary($data['parent_checksum']);
        return self::create($data)->store($db);
    }

}
