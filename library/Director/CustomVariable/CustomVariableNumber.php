<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;

class CustomVariableNumber extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableNumber) {
            return false;
        }

        // TODO: in case we encounter problems with floats we could
        //       consider something as follows, but taking more care
        //       about precision:
        /*
        if (is_float($this->value)) {
            return sprintf($var->getValue(), '%.9F')
                === sprintf($this->getValue(), '%.9F');
        }
        */
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
        if (is_int($this->value)) {
            return (string) $this->value;
        } else {
            // Hint: this MUST NOT respect locales
            return sprintf('%F', $this->value);
        }
    }
}
