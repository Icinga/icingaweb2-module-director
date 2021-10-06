<?php

namespace Icinga\Module\Director\Objects;

class IcingaUserGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_usergroup';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'zone_id'       => null,
    ];

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    protected function prefersGlobalZone()
    {
        return false;
    }
}
