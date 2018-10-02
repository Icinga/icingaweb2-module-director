<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierTrim extends PropertyModifierHook
{
    public function transform($value)
    {
        return trim($value);
    }
}
