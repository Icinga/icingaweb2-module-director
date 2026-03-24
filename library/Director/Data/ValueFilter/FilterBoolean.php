<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data\ValueFilter;

use Icinga\Module\Director\Data\ValueFilter;

class FilterBoolean implements ValueFilter
{
    public function filter($value)
    {
        if ($value === 'y' || $value === true) {
            return true;
        } elseif ($value === 'n' || $value === false) {
            return false;
        }

        return null;
    }
}
