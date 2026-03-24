<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierUppercase extends PropertyModifierHook
{
    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        return \mb_strtoupper($value, 'UTF-8');
    }
}
