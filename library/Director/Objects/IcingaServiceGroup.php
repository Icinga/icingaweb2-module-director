<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_servicegroup';

    public function supportsAssignments()
    {
        return true;
    }
}
