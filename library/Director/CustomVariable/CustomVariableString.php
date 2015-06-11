<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfigHelper as c;

class CustomVariableString extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        return $var->getValue() === $this->getValue();
    }

    public function toConfigString()
    {
        return c::renderKeyValue(
            c::escapeIfReserved($this->getKey()),
            c::renderString($this->getValue()
        );
    }
}
