<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierUppercase extends PropertyModifierHook
{
    public function transform($value)
    {
        return \mb_strtoupper($value, 'UTF-8');
    }
}
