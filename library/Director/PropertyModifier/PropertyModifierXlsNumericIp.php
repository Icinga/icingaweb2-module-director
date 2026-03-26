<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierXlsNumericIp extends PropertyModifierHook
{
    public function getName()
    {
        return 'Fix IP formatted as a number in MS Excel';
    }

    public function transform($value)
    {
        if (ctype_digit($value) && strlen($value) > 9 && strlen($value) <= 12) {
            return preg_replace(
                '/^(\d{1,3})(\d{3})(\d{3})(\d{3})/',
                '\1.\2.\3.\4',
                $value
            );
        } else {
            return $value;
        }
    }
}
