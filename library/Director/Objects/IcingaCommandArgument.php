<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaCommandArgument extends IcingaObject
{
    protected $keyName = 'id';

    protected $table = 'icinga_command_argument';

    protected $defaultProperties = array(
        'id'              => null,
        'command_id'      => null,
        'argument_name'   => null,
        'argument_value'  => null,
        'argument_format' => null,
        'key_string'      => null,
        'description'     => null,
        'skip_key'        => null,
        'set_if'          => null,
        'sort_order'      => null,
        'repeat_key'      => null,
        'set_if_format'   => null,
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

    public function toConfigString()
    {
        $data = array();
        if ($this->argument_value) {
            switch ($this->argument_format) {
                case 'string':
                    $data['value'] = c::renderString($this->argument_value);
                    break;
            }
        }

        if ($this->sort_order) {
            $data['order'] = $this->sort_order;
        }

        return c::renderDictionary($data);
    }
}
