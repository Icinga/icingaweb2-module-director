<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceField extends IcingaObjectField
{
    protected $keyName = array('service_id', 'datafield_id');

    protected $table = 'icinga_service_field';

    protected $defaultProperties = array(
        'service_id'   => null,
        'datafield_id' => null,
        'is_required'  => null,
        'var_filter'   => null,
    );
}
