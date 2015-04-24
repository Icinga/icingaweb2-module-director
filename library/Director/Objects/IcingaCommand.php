<?php

namespace Icinga\Module\Director\Objects;

class IcingaCommand extends IcingaObject
{
    protected $table = 'icinga_command';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'methods_execute'       => null,
        'command'               => null,
        'timeout'               => null,
        'zone_id'               => null,
        'object_type'           => null,
    );
}
