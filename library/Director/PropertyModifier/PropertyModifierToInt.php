<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierToInt extends PropertyModifierHook
{
    public function getName()
    {
        return 'Cast a string value to an Integer';
    }

    public function transform($value)
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return (int) $value;
        }
    }
}
