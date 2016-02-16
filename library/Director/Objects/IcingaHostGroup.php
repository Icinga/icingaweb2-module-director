<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroup extends IcingaObject
{
    protected $table = 'icinga_hostgroup';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
    );
}
