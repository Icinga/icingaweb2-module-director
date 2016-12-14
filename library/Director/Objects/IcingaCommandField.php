<?php

namespace Icinga\Module\Director\Objects;

class IcingaCommandField extends IcingaObjectField
{
    protected $keyName = array('command_id', 'datafield_id');

    protected $table = 'icinga_command_field';

    protected $defaultProperties = array(
        'command_id'   => null,
        'datafield_id' => null,
        'is_required'  => null,
        'var_filter'   => null,
    );
}
