<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceGroupMember extends IcingaObject
{
    protected $keyName = array('service_id', 'servicegroup_id');

    protected $table = 'icinga_servicegroup_service';

    protected $defaultProperties = array(
        'servicegroup_id'      => null,
        'service_id'           => null,
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