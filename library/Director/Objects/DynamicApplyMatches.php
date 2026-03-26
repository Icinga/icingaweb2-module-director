<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
