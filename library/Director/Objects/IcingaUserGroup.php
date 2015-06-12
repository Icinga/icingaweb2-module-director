<?php

namespace Icinga\Module\Director\Objects;

class IcingaUserGroup extends IcingaObject
{
    protected $table = 'icinga_usergroup';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'display_name'          => null,
        'object_type'           => null,
        'zone_id'               => null,
    );
}
