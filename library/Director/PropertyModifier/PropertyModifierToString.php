<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Data\InvalidDataException;
use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierToString extends PropertyModifierHook
{
    public function getName()
    {
        return 'Cast an integer value to a string';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new InvalidDataException('String, integer or null', $value);
    }
}
