<?php

namespace Icinga\Module\Director\Core;

use Icinga\Module\Director\Exception\JsonEncodeException;

class Json
{
    public static function encode($string)
    {
        $result = json_encode($string);

        if ($result === false) {
            throw JsonEncodeException::forLastJsonError();
        }

        return $result;
    }
}
