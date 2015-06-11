<?php

namespace Icinga\Module\Director;

class IcingaConfigHelper
{
    public static function renderKeyValue($key, $value, $prefix = '')
    {
        return sprintf(
            "%s    %s = %s\n",
            $prefix,
            $key,
            $value
        );
    }

    public static function renderBoolean($value)
    {
        if ($value === 'y') {
            return 'true';
        } elseif ($value === 'n') {
            return 'false';
        } else {
            throw new ProgrammingError('%s is not a valid boolean', $value);
        }
    }

    public static function renderString($string)
    {
        $string = preg_replace('~\\\~', '\\\\', $string);
        $string = preg_replace('~"~', '\\"', $string);

        return sprintf('"%s"', $string);
    }
}
