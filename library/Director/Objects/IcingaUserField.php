<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaUserField extends DbObject
{
    protected $keyName = array('user_id', 'datafield_id');

    protected $table = 'icinga_user_field';

    protected $defaultProperties = array(
        'user_id'       => null,
        'datafield_id'  => null,
        'is_required'   => null
    );
}
