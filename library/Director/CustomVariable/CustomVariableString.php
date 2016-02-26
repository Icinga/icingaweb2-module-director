<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class CustomVariableString extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableString) {
            return false;
        }

        return $var->getValue() === $this->getValue();
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        if (! is_string($value)) {
            $value = (string) $value;
        }

        if ($value !== $this->value) {
            $this->value = $value;
            $this->setModified();
        }

        return $this;
    }

    public function toConfigString()
    {
        return c::renderString($this->getValue());
    }
}
