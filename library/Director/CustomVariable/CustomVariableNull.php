<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;

class CustomVariableNull extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        return $var instanceof CustomVariableNull;
    }

    public function getValue()
    {
        return null;
    }

    public function getDbValue()
    {
        return json_encode($this->getValue());
    }

    public function getDbFormat()
    {
        return 'json';
    }

    public function setValue($value)
    {
        if (! is_null($value)) {
            throw new ProgrammingError(
                'Null can only be null, got %s',
                var_export($value, true)
            );
        }

        $this->deleted = false;

        return $this;
    }

    public function toConfigString($renderExpressions = false)
    {
        return 'null';
    }

    public function toLegacyConfigString()
    {
        return $this->toConfigString();
    }
}
