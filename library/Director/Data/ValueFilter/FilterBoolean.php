<?php

namespace Icinga\Module\Director\Data\ValueFilter;

use Icinga\Module\Director\Data\ValueFilter;

class FilterBoolean implements ValueFilter
{
    public function filter($value)
    {
        if ($value === 'y' || $value === true) {
            return true;
        } elseif ($value === 'n' || $value === false) {
            return false;
        }

        return null;
    }
}
