<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaTimePeriodRange extends DbObject
{
    protected $keyName = array('timeperiod_id', 'timeperiod_key', 'range_type');

    protected $table = 'icinga_timeperiod_range';

    protected $defaultProperties = array(
        'timeperiod_id'       => null,
        'timeperiod_key'      => null,
        'timeperiod_value'    => null,
        'range_type'          => 'include',
        'merge_behaviour'     => 'set',
    );
}
