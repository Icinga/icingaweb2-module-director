<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostgroupMember extends IcingaObject
{
    protected $keyName = array('host_id', 'hostgroup_id');

    protected $table = 'icinga_hostgroup_host';

    protected $defaultProperties = array(
        'hostgroup_id'      => null,
        'host_id'           => null,
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