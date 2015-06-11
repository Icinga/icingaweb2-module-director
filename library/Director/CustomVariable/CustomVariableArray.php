<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfigHelper as c;

class CustomVariableArray extends CustomVariable
{
    public function equals(CustomVariable $var)
    {
        if (! $var instanceof CustomVariableArray) {
            return false;
        }

        return $var->getValue() === $this->getValue();
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
        $str = '[ ' . implode(', ', $this->value) . ' ]';

        if (strlen($str) < 60) {
            return $str;
        }

        // Prefix for toConfigString?
        return "[\n    " . implode(",\n    ", $this->value) . "\n]";
    }
}
