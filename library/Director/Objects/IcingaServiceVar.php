<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceVar extends IcingaObject
{
    protected $keyName = array('service_id', 'varname');

    protected $table = 'icinga_service_var';

    protected $defaultProperties = array(
        'service_id'   => null,
        'varname'   => null,
        'varvalue'  => null,
        'format'    => null,
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
