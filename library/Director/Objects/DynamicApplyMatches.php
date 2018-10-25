<?php

namespace Icinga\Module\Director\Objects;

class DynamicApplyMatches extends ObjectApplyMatches
{
    protected static $type = '';

    public static function setType($type)
    {
        static::$type = $type;
        return static::$type;
    }
}
