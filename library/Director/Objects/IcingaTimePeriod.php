<?php

namespace Icinga\Module\Director\Objects;

class IcingaTimePeriod extends IcingaObject
{
    protected $table = 'icinga_timeperiod';

    protected $defaultProperties = array(
        'id'                    => null,
        'zone_id'               => null,
        'object_name'           => null,
        'display_name'          => null,
        'update_method'         => null,
        'object_type'           => null,
    );
}
