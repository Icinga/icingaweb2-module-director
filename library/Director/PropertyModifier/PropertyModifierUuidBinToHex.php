<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Ramsey\Uuid\Uuid;

class PropertyModifierUuidBinToHex extends PropertyModifierHook
{
    public function getName()
    {
        return mt('director', 'UUID: from binary to hex');
    }

    public function transform($value)
    {
        return Uuid::fromBytes($value)->toString();
    }
}
