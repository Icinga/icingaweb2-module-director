<?php

namespace Icinga\Module\Director\Objects;

class IcingaUserGroup extends IcingaObject
{
    protected $table = 'icinga_usergroup';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
        'zone_id'               => null,
    );
}
