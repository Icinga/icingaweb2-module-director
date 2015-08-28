<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class CustomVariableArray extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableArray) {
            return false;
        }

        return $var->getDbValue() === $this->getDbValue();
    }

    public function getValue()
    {
        $ret = array();
        foreach ($this->value as $var) {
            $ret[] = $var->getValue();
        }

        return $ret;
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
        $new = array();

        foreach ($value as $key => $val) {
            $new[$key] = self::wantCustomVariable($key, $val);
        }

        // WTF?
        if ($this->value === $new) {
            return $this;
        }

        $this->value = $new;
        $this->setModified();

        return $this;
    }

    public function toConfigString()
    {
        return c::renderArray($this->value);
    }
}
