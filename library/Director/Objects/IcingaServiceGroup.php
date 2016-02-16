<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceGroup extends IcingaObject
{
    protected $table = 'icinga_servicegroup';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
    );
}
