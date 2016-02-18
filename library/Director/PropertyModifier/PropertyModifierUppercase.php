<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierUppercase extends PropertyModifierHook
{
    public function transform($value)
    {
        return strtoupper($value);
    }
}
