<?php

// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierRenameColumn extends PropertyModifierHook
{
    public function getName()
    {
        return 'Rename a Property/Column';
    }

    public function requiresRow()
    {
        return true;
    }

    public function hasArraySupport()
    {
        return true;
    }

    public function transform($value)
    {
        $row = $this->getRow();
        $property = $this->getPropertyName();
        if ($row) {
            unset($row->$property);
        }
        // $this->rejectRow();
        return $value;
    }
}
