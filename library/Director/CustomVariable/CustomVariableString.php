<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

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

        $this->deleted = false;

        return $this;
    }

    public function flatten(array &$flat, $prefix)
    {
        // TODO: we should get rid of type=string and always use JSON
        $flat[$prefix] = json_encode($this->getValue());
    }

    public function toConfigString($renderExpressions = false)
    {
        if ($renderExpressions) {
            return c::renderStringWithVariables($this->getValue(), ['config'], true);
        } else {
            return c::renderString($this->getValue());
        }
    }

    public function toLegacyConfigString()
    {
        return c1::renderString($this->getValue());
    }
}
