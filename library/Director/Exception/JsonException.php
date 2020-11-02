<?php

namespace Icinga\Module\Director\Exception;

use Icinga\Exception\IcingaException;

class JsonException extends IcingaException
{
    public static function forLastJsonError($msg = null)
    {
        if ($msg === null) {
            return new static(static::getJsonErrorMessage(\json_last_error()));
        } else {
            return new static($msg . ': ' . static::getJsonErrorMessage(\json_last_error()));
        }
    }

    public static function getJsonErrorMessage($code)
    {
        $map = [
            JSON_ERROR_DEPTH          => 'The maximum stack depth has been exceeded',
            JSON_ERROR_CTRL_CHAR      => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_SYNTAX         => 'JSON Syntax error',
            JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        ];
        if (\array_key_exists($code, $map)) {
            return $map[$code];
        }

        if (PHP_VERSION_ID >= 50500) {
            $map = [
                JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
                JSON_ERROR_INF_OR_NAN => 'One or more NAN or INF values in the value to be encoded',
                JSON_ERROR_UNSUPPORTED_TYPE => 'A value of a type that cannot be encoded was given',
            ];
            if (\array_key_exists($code, $map)) {
                return $map[$code];
            }
        }

        if (PHP_VERSION_ID >= 70000) {
            $map = [
                JSON_ERROR_INVALID_PROPERTY_NAME => 'A property name that cannot be encoded was given',
                JSON_ERROR_UTF16 => 'Malformed UTF-16 characters, possibly incorrectly encoded',
            ];

            if (\array_key_exists($code, $map)) {
                return $map[$code];
            }
        }

        return 'An error occured when parsing a JSON string';
    }
}
