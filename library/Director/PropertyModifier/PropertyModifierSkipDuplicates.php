<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierSkipDuplicates extends PropertyModifierHook
{
    private $seen = [];

    public function getName()
    {
        return mt('director', 'Skip row if this value appears more than once');
    }

    public function transform($value)
    {
        if (isset($this->seen[$value])) {
            $this->rejectRow();
        }

        $this->seen[$value] = true;

        return $value;
    }
}
