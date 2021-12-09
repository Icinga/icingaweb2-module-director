<?php

namespace Icinga\Module\Director\Data;

use InvalidArgumentException;

class InvalidDataException extends InvalidArgumentException
{
    /**
     * @param string $expected
     * @param mixed $value
     */
    public function __construct($expected, $value)
    {
        parent::__construct("$expected expected, got " . static::getPhpType($value));
    }

    public static function getPhpType($var)
    {
        if (is_object($var)) {
            return get_class($var);
        }

        return gettype($var);
    }
}
