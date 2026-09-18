<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Form\Element;

/**
 * Input control for booleans, gives y/n
 */
class OptionalYesNo extends Boolean
{
    public function getValue()
    {
        $value = $this->getUnfilteredValue();

        if ($value === 'y' || $value === true) {
            return 'y';
        } elseif ($value === 'n' || $value === false) {
            return 'n';
        }

        return null;
    }
}
