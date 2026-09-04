<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostVar extends IcingaObject
{
    protected $keyName = array('host_id', 'varname');

    protected $table = 'icinga_host_var';

    protected $defaultProperties = array(
        'host_id'   => null,
        'varname'   => null,
        'varvalue'  => null,
        'format'    => null,
        'property_uuid' => null,
    );

    public function onInsert()
    {
    }

    public function onUpdate()
    {
    }

    public function onDelete()
    {
    }
}
