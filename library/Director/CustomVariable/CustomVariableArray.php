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

        foreach ($value as $k => $v) {
            $new[] = self::wantCustomVariable($k, $v);
        }

        $equals = true;
        if (is_array($this->value) && count($new) === count($this->value)) {
            foreach ($this->value as $k => $v) {
                if (! $new[$k]->equals($v)) {
                    $equals = false;
                    break;
                }
            }
        } else {
            $equals = false;
        }

        if (! $equals) {
            $this->value = $new;
            $this->setModified();
        }

        return $this;
    }

    public function toConfigString()
    {
        return c::renderArray($this->value);
    }

    public function __clone()
    {
        foreach ($this->value as $key => $value) {
            $this->value[$key] = clone($value);
        }
    }
}
