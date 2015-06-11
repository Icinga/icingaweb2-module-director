<?php

namespace Icinga\Module\Director\CustomVariable;

class CustomVariableArray extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableArray) {
            return false;
        }

        return $var->getValue() === $this->getValue();
    }

    public function toConfigString()
    {
        // TODO: Implement toConfigString() method.
    }

}
