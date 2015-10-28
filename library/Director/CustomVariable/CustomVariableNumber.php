<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;

class CustomVariableNumber extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        return $var->getValue() === $this->getValue();
    }

    public function getDbFormat()
    {
        return 'json';
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new ProgrammingError(
                'Expected a number, got %s',
                var_export($value, 1)
            );
        }

        $this->value = $value;

        return $this;
    } 

    public function toConfigString()
    {
        return (string) $this->value;
    }
}
