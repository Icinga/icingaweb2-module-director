<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use function array_unique;
use function array_values;
use function is_array;

class PropertyModifierArrayUnique extends PropertyModifierHook
{
    public function getName()
    {
        return 'Unique Array Values';
    }

    public function hasArraySupport()
    {
        return true;
    }

    public function transform($value)
    {
        if (empty($value)) {
            return $value;
        }

        if (! is_array($value)) {
            throw new InvalidPropertyException(
                'The ArrayUnique property modifier can be applied to arrays only'
            );
        }

        return array_values(array_unique($value));
    }
}
