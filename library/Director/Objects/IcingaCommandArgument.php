<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaCommandArgument extends IcingaObject
{
    protected $keyName = 'id';

    protected $table = 'icinga_command_argument';

    protected $supportsImports = false;

    protected $booleans = array(
        'skip_key'   => 'skip_key',
        'repeat_key' => 'repeat_key',
        'required'   => 'required'
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
        'required'        => null,
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

    public function isSkippingKey()
    {
        return $this->skip_key === 'y' || $this->argument_name === null;
    }

    // Preserve is not supported
    public function replaceWith(IcingaObject $object, $preserve = null)
    {
        $this->setProperties((array) $object->toPlainObject(
            false,
            false,
            null,
            false
        ));
        return $this;
    }

    protected function makePlainArgumentValue($value, $format)
    {
        if ($format === 'expression') {
            return (object) [
                'type' => 'Function',
                // TODO: Not for dummy comment
                'body' => $value
            ];
        } else {
            // json or string
            return $value;
        }
    }

    protected function extractValueFromPlain($plain)
    {
        if ($plain->argument_value) {
            return $this->makePlainArgumentValue(
                $plain->argument_value,
                $plain->argument_format
            );
        } else {
            return null;
        }
    }

    protected function transformPlainArgumentValue($plain)
    {
        if (property_exists($plain, 'argument_value')) {
            $plain->value = $this->makePlainArgumentValue(
                $plain->argument_value,
                $plain->argument_format
            );
            unset($plain->argument_value);
            unset($plain->argument_format);
        }
    }

    public function toCompatPlainObject()
    {
        $plain = parent::toPlainObject(
            false,
            true,
            null,
            false
        );

        unset($plain->id);
        unset($plain->argument_name);

        $this->transformPlainArgumentValue($plain);

        // Will happen only combined with $skipDefaults
        if (array_keys((array) $plain) === ['value']) {
            return $plain->value;
        } else {
            if (property_exists($plain, 'sort_order') && $plain->sort_order !== null) {
                $plain->order = $plain->sort_order;
                unset($plain->sort_order);
            }

            return $plain;
        }
    }

    public function toFullPlainObject($skipDefaults = false)
    {
        $plain = parent::toPlainObject(
            false,
            $skipDefaults,
            null,
            false
        );

        unset($plain->id);
        unset($plain->argument_format);

        return $plain;
    }

    public function toPlainObject(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null,
        $resolveIds = true
    ) {
        if ($resolved) {
            throw new ProgrammingError(
                'A single CommandArgument cannot be resolved'
            );
        }

        if ($chosenProperties) {
            throw new ProgrammingError(
                'IcingaCommandArgument does not support chosenProperties[]'
            );
        }

        // $resolveIds is misused here
        if ($resolveIds) {
            return $this->toCompatPlainObject();
        } else {
            return $this->toFullPlainObject($skipDefaults);
        }
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

        if ($this->sort_order !== null) {
            $data['order'] = $this->sort_order;
        }

        if ($this->set_if) {
            switch ($this->set_if_format) {
                case 'expression':
                    $data['set_if'] = c::renderExpression($this->set_if);
                    break;
                case 'string':
                default:
                    $data['set_if'] = c::renderString($this->set_if);
                    break;
            }
        }

        if ($this->required) {
            $data['required'] = c::renderBoolean($this->required);
        }

        if ($this->isSkippingKey()) {
            $data['skip_key'] = c::renderBoolean('y');
        }

        if ($this->repeat_key) {
            $data['repeat_key'] = c::renderBoolean($this->repeat_key);
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
