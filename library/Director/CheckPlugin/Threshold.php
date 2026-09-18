<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CheckPlugin;

class Threshold
{
    /** @var Range */
    protected $warning;

    /** @var Range */
    protected $critical;

    public function __construct($warning = null, $critical = null)
    {
        if ($warning !== null) {
            $this->warning = Range::wantRange($warning);
        }

        if ($critical !== null) {
            $this->critical = Range::wantRange($critical);
        }
    }

    public static function check($value, $message, $warning = null, $critical = null)
    {
        $threshold = new static($warning, $critical);
        $state = $threshold->checkValue($value);
        return new CheckResult($message, $state);
    }

    public function checkValue($value)
    {
        if ($this->critical !== null) {
            if (! $this->critical->valueIsValid($value)) {
                return PluginState::critical();
            }
        }

        if ($this->warning !== null) {
            if (! $this->warning->valueIsValid($value)) {
                return PluginState::warning();
            }
        }

        return PluginState::ok();
    }
}
