<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierLowercase extends PropertyModifierHook
{
    public function transform($value)
    {
        return strtolower($value);
    }
}
