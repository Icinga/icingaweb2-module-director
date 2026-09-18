<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data\PropertiesFilter;

use Icinga\Module\Director\Data\PropertiesFilter;

class CustomVariablesFilter extends PropertiesFilter
{
    public function match($type, $name, $object = null)
    {
        return parent::match($type, $name, $object) && $type === self::$CUSTOM_PROPERTY;
    }
}
