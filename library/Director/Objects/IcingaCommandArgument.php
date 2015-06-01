<?php

namespace Icinga\Module\Director\Objects;

class IcingaCommandArgument extends IcingaObject
{
    protected $table = 'icinga_command_argument';

    protected $defaultProperties = array(
        'id'             => null,
        'command_id'     => null,
        'argument_name'  => null,
        'argument_value' => null,
        'key_string'     => null,
        'description'    => null,
        'skip_key'       => null,
        'set_if'         => null,
        'sort_order'     => null,
        'repeat_key'     => null,
        'value_format'   => null,
        'set_if_format'  => null,
    );

    public function onInsert()
    {
        // No log right now, we have to handle "sub-objects"
    }

    public function onUpdate()
    {
        // No log right now, we have to handle "sub-objects"
    }

    public function onDelete()
    {
        // No log right now, we have to handle "sub-objects"
    }
}
