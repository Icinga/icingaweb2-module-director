<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostField extends IcingaObjectField
{
    protected $keyName = array('host_id', 'datafield_id');

    protected $table = 'icinga_host_field';

    protected $defaultProperties = array(
        'host_id'      => null,
        'datafield_id' => null,
        'is_required'  => null,
        'var_filter'   => null,
    );
}
