<?php

namespace Icinga\Module\Director\Data;

use InvalidArgumentException;
use function array_diff;
use function array_key_exists;
use function implode;

class DataArrayHelper
{
    public static function wantArray($value)
    {
        if (is_object($value)) {
            return (array) $value;
        } elseif (! is_array($value)) {
            throw new InvalidDataException('Object', $value);
        }

        return $value;
    }

    public static function failOnUnknownProperties(array $values, array $knownProperties)
    {
        $unknownProperties = array_diff($knownProperties, array_keys($values));

        if (! empty($unknownProperties)) {
            throw new InvalidArgumentException('Unexpected properties: ' . implode(', ', $unknownProperties));
        }
    }

    public static function requireProperties(array $value, array $properties)
    {
        $missing = [];
        foreach ($properties as $property) {
            if (! array_key_exists($property, $value)) {
                $missing[] = $property;
            }
        }

        if (! empty($missing)) {
            throw new InvalidArgumentException('Missing properties: ' . implode(', ', $missing));
        }
    }
}