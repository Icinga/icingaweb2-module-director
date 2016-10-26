<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Config;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;

abstract class IcingaObjectGroup extends IcingaObject
{
    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'            => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'assign_filter' => null,
    );

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getDefaultGlobalZoneName();
    }

    public static function enumForType($type, Db $connection = null)
    {
        if ($connection === null) {
            // TODO: not nice :(
            $connection = Db::fromResourceName(
                Config::module('director')->get('db', 'resource')
            );
        }

        // Last resort defense against potentiall lousy checks:
        if (! ctype_alpha($type)) {
            throw new IcingaException(
                'Holy shit, you should never have reached this'
            );
        }

        $db = $connection->getDbAdapter();
        $select = $db->select()->from(
            'icinga_' . $type . 'group',
            array(
                'name'    => 'object_name',
                'display' => 'COALESCE(display_name, object_name)'
            )
        )->where('object_type = ?', 'object')->order('display');

        return $db->fetchPairs($select);
    }
}
