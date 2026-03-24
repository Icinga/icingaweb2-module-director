<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Core;

use Icinga\Module\Director\Exception\JsonEncodeException;

class Json
{
    public static function encode($mixed, $flags = null)
    {
        if ($flags === null) {
            $result = \json_encode($mixed);
        } else {
            $result = \json_encode($mixed, $flags);
        }

        if ($result === false && json_last_error() !== JSON_ERROR_NONE) {
            throw JsonEncodeException::forLastJsonError();
        }

        return $result;
    }

    public static function decode($string)
    {
        $result = \json_decode($string);

        if ($result === null && json_last_error() !== JSON_ERROR_NONE) {
            throw JsonEncodeException::forLastJsonError();
        }

        return $result;
    }
}
