<?php

namespace Icinga\Module\Director\Db;

use function is_resource;
use function stream_get_contents;

class DbUtil
{
    public static function binaryResult($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }
}
