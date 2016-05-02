<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class IcingaUserGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_usergroup';

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getMasterZoneName();
    }
}
