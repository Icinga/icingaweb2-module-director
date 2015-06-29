<?php

namespace Icinga\Module\Director\Objects;

class IcingaEndpoint extends IcingaObject
{
    protected $table = 'icinga_endpoint';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'zone_id'               => null,
        'object_name'           => null,
        'address'               => null,
        'port'                  => null,
        'log_duration'          => null,
        'object_type'           => null,
    );
}
