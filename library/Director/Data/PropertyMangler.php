<?php

namespace Icinga\Module\Director\Data;

use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;

class PropertyMangler
{
    public static function appendToArrayProperties(IcingaObject $object, $properties)
    {
        foreach ($properties as $key => $value) {
            $current = $object->$key;
            if ($current === null) {
                $current = [$value];
            } elseif (is_array($current)) {
                $current[] = $value;
            } else {
                throw new InvalidArgumentException(sprintf(
                    'I can only append to arrays, %s is %s',
                    $key,
                    var_export($current, true)
                ));
            }

            $object->$key = $current;
        }
    }

    public static function removeProperties(IcingaObject $object, $properties)
    {
        foreach ($properties as $key => $value) {
            if ($value === true) {
                $object->$key = null;
            }
            $current = $object->$key;
            if ($current === null) {
                continue;
            } elseif (is_array($current)) {
                $new = [];
                foreach ($current as $item) {
                    if ($item !== $value) {
                        $new[] = $item;
                    }
                }
                $object->$key = $new;
            } elseif (is_string($current)) {
                if ($current === $value) {
                    $object->$key = null;
                }
            } else {
                throw new InvalidArgumentException(sprintf(
                    'I can only remove strings or from arrays, %s is %s',
                    $key,
                    var_export($current, true)
                ));
            }
        }
    }
}
