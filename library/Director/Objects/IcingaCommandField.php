<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaCommandField extends DbObject
{
    protected $keyName = array('command_id', 'datafield_id');

    protected $table = 'icinga_command_field';

    protected $defaultProperties = array(
        'command_id'    => null,
        'datafield_id'  => null,
        'is_required'   => null
    );
}
