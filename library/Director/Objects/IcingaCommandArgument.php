<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaCommandArgument extends IcingaObject
{
    protected $keyName = 'id';

    protected $table = 'icinga_command_argument';

    protected $booleans = array(
        'skip_key'   => 'skip_key',
        'repeat_key' => 'repeat_key'
    );

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
                case 'json':
                    if (is_object($this->argument_value)) {
                        $data['value'] = c::renderDictionary($this->argument_value);
                    } elseif (is_array($this->argument_value)) {
                        $data['value'] = c::renderArray($this->argument_value);
                    } elseif (is_null($this->argument_value)) {
                        // TODO: recheck all this. I bet we never reach this:
                        $data['value'] = 'null';
                    } elseif (is_bool($this->argument_value)) {
                        $data['value'] = c::renderBoolean($this->argument_value);
                    } else {
                        $data['value'] = $this->argument_value;
                    }
                    break;
                case 'expression':
                    $data['value'] = c::renderExpression($this->argument_value);
                    break;
            }
        }

        if ($this->sort_order) {
            $data['order'] = $this->sort_order;
        }

        if ($this->set_if) {
            $data['set_if'] = c::renderString($this->set_if);
        }

        if ((int) $this->sort_order !== 0) {
            $data['order'] = $this->sort_order;
        }

        if ($this->description) {
            $data['description'] = c::renderString($this->description);
        }

        if (array_keys($data) === array('value')) {
            return $data['value'];
        } else {
            return c::renderDictionary($data);
        }
    }
}
