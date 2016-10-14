<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;

class CustomVariableNumber extends CustomVariable
{
    // Hint: 'F' is intentional, this MUST NOT respect locales
    const PRECISION = '%.9F';

    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableNumber) {
            return false;
        }

        $cur = $this->getValue();
        $new = $var->getValue();

        // Be tolerant when comparing floats:
        if (is_float($cur) || is_float($new)) {
            return sprintf(self::PRECISION, $cur)
                === sprintf(self::PRECISION, $new);
        }

        return $cur === $new;
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
        $this->deleted = false;

        return $this;
    }

    public function toConfigString()
    {
        if (is_int($this->value)) {
            return (string) $this->value;
        } else {
            return sprintf(self::PRECISION, $this->value);
        }
    }

    public function toLegacyConfigString()
    {
        return $this->toConfigString();
    }
}
