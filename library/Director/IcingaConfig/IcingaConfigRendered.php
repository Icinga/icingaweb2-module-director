<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\IcingaConfig;

use InvalidArgumentException;

class IcingaConfigRendered implements IcingaConfigRenderer
{
    protected $rendered;

    public function __construct($string)
    {
        if (! is_string($string)) {
            throw new InvalidArgumentException('IcingaConfigRendered accepts only strings');
        }

        $this->rendered = $string;
    }

    public function toConfigString()
    {
        return $this->rendered;
    }

    public function __toString()
    {
        return $this->toConfigString();
    }

    public function toLegacyConfigString()
    {
        return $this->rendered;
    }
}
