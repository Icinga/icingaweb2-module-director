<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;

class CustomVariableBoolean extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        return $var->getValue() === $this->getValue();
    }

    public function getDbFormat()
    {
        return 'json';
    }

    public function getDbValue()
    {
        return json_encode($this->getValue());
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        if (! is_bool($value)) {
            throw new ProgrammingError(
                'Expected a boolean, got %s',
                var_export($value, 1)
            );
        }

        $this->value = $value;
        $this->deleted = false;

        return $this;
    }

    public function toConfigString()
    {
        return $this->value ? 'true' : 'false';
    }

    public function toLegacyConfigString()
    {
        return $this->toConfigString();
    }
}
