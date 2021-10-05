<?php

namespace Icinga\Module\Director\Data;

use Icinga\Module\Director\Exception\JsonEncodeException;
use function json_decode;
use function json_encode;
use function json_last_error;

class Json
{
    const DEFAULT_FLAGS = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Encode with well-known flags, as we require the result to be reproducible
     *
     * @param $mixed
     * @param int|null $flags
     * @return string
     * @throws JsonEncodeException
     */
    public static function encode($mixed, $flags = null)
    {
        if ($flags === null) {
            $flags = self::DEFAULT_FLAGS;
        } else {
            $flags = self::DEFAULT_FLAGS | $flags;
        }
        $result = json_encode($mixed, $flags);

        if ($result === false && json_last_error() !== JSON_ERROR_NONE) {
            throw JsonEncodeException::forLastJsonError();
        }

        return $result;
    }

    /**
     * Decode the given JSON string and make sure we get a meaningful Exception
     *
     * @param string $string
     * @return mixed
     * @throws JsonEncodeException
     */
    public static function decode($string)
    {
        $result = json_decode($string);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw JsonEncodeException::forLastJsonError();
        }

        return $result;
    }

    /**
     * @param $string
     * @return ?string
     * @throws JsonEncodeException
     */
    public static function decodeOptional($string)
    {
        if ($string === null) {
            return null;
        }

        return static::decode($string);
    }
}
