<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data\ValueFilter;

use Icinga\Module\Director\Data\ValueFilter;

class FilterInt implements ValueFilter
{
    public function filter($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_string($value) && ! ctype_digit($value)) {
            return $value;
        }

        return (int) ((string) $value);
    }
}
