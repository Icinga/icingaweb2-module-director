<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use function ipl\Stdlib\get_php_type;

class PropertyModifierNegateBoolean extends PropertyModifierHook
{
    public function getName()
    {
        return 'Negate a boolean value';
    }

    public function transform($value)
    {
        if ($value === null) {
            return true;
        }
        if (! is_bool($value)) {
            throw new \InvalidArgumentException('Boolean expected, got ' . get_php_type($value));
        }

        return ! $value;
    }
}
