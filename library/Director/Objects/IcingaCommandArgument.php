<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use RuntimeException;

class IcingaCommandArgument extends IcingaObject
{
    protected $keyName = ['command_id', 'argument_name'];

    protected $autoincKeyName = 'id';

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
        return $this->get('skip_key') === 'y' || $this->get('argument_name') === null;
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
            if (property_exists($plain, 'argument_format')) {
                $format = $plain->argument_format;
            } else {
                $format = 'string';
            }
            $plain->value = $this->makePlainArgumentValue(
                $plain->argument_value,
                $format
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
        if (! isset($plain->argument_value)) {
            unset($plain->argument_format);
        }
        if (! isset($plain->set_if)) {
            unset($plain->set_if_format);
        }

        $this->transformPlainArgumentValue($plain);
        unset($plain->command_id);

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

        return $plain;
    }

    public function toPlainObject(
        $resolved = false,
        $skipDefaults = false,
        ?array $chosenProperties = null,
        $resolveIds = true,
        $keepId = false
    ) {
        if ($resolved) {
            throw new RuntimeException(
                'A single CommandArgument cannot be resolved'
            );
        }

        if ($chosenProperties) {
            throw new RuntimeException(
                'IcingaCommandArgument does not support chosenProperties[]'
            );
        }

        if ($keepId) {
            throw new RuntimeException(
                'IcingaCommandArgument does not support $keepId'
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
        $value = $this->get('argument_value');
        if ($value) {
            switch ($this->get('argument_format')) {
                case 'string':
                    $data['value'] = c::renderString($value);
                    break;
                case 'json':
                    if (is_object($value)) {
                        $data['value'] = c::renderDictionary($value);
                    } elseif (is_array($value)) {
                        $data['value'] = c::renderArray($value);
                    } elseif (is_null($value)) {
                        // TODO: recheck all this. I bet we never reach this:
                        $data['value'] = 'null';
                    } elseif (is_bool($value)) {
                        $data['value'] = c::renderBoolean($value);
                    } else {
                        $data['value'] = $value;
                    }
                    break;
                case 'expression':
                    $data['value'] = c::renderExpression($value);
                    break;
            }
        }

        if ($this->get('sort_order') !== null) {
            $data['order'] = $this->get('sort_order');
        }

        if (null !== $this->get('set_if')) {
            switch ($this->get('set_if_format')) {
                case 'expression':
                    $data['set_if'] = c::renderExpression($this->get('set_if'));
                    break;
                case 'string':
                default:
                    $data['set_if'] = c::renderString($this->get('set_if'));
                    break;
            }
        }

        if (null !== $this->get('required')) {
            $data['required'] = c::renderBoolean($this->get('required'));
        }

        if ($this->isSkippingKey()) {
            $data['skip_key'] = c::renderBoolean('y');
        }

        if (null !== $this->get('repeat_key')) {
            $data['repeat_key'] = c::renderBoolean($this->get('repeat_key'));
        }

        if (null !== $this->get('description')) {
            $data['description'] = c::renderString($this->get('description'));
        }

        if (array_keys($data) === ['value']) {
            return $data['value'];
        } else {
            return c::renderDictionary($data);
        }
    }
}
