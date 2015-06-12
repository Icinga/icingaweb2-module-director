<?php

namespace Icinga\Module\Director\Objects;

class IcingaUserGroupMember extends IcingaObject
{
    protected $keyName = array('user_id', 'usergroup_id');

    protected $table = 'icinga_usergroup_user';

    protected $defaultProperties = array(
        'usergroup_id'      => null,
        'user_id'           => null,
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