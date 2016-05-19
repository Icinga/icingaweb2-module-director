<?php

namespace Icinga\Module\Director\Data\ValueFilter;

use Icinga\Module\Director\Data\ValueFilter;

class FilterInt implements ValueFilter
{
    public function filter($value)
    {
        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value)) {
            return $value;
        }

        return (int) ((string) $value);
    }
}
