<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class IcingaUserGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_usergroup';

    protected $defaultProperties = [
        'id'            => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
    ];

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getMasterZoneName();
    }
}
