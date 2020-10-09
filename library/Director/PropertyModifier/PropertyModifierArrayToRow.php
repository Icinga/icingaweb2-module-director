<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierArrayToRow extends PropertyModifierHook
{
    public function getName()
    {
        return 'Clone the row for every entry of an Array';
    }

    public function hasArraySupport()
    {
        return true;
    }

    public function expandsRows()
    {
        return true;
    }

    public function transform($value)
    {
        if (empty($value)) {
            $this->rejectRow();
            return null;
        }

        return $value;
    }
}
