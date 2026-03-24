<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierURLEncode extends PropertyModifierHook
{
    public function getName()
    {
        return 'URL-encode a string';
    }


    public function transform($value)
    {
        return rawurlencode($value);
    }
}
