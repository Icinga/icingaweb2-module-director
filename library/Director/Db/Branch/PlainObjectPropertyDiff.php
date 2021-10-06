<?php

namespace Icinga\Module\Director\Db\Branch;

class PlainObjectPropertyDiff
{
    public static function calculate(array $old = null, array $new = null)
    {
        if ($new === null) {
            throw new \RuntimeException('Cannot diff for delete');
        }
        if ($old === null) {
            foreach (BranchSettings::ENCODED_DICTIONARIES as $property) {
                self::flattenProperty($new, $property);
            }

            return $new;
        }
        $unchangedKeys = [];
        foreach (BranchSettings::ENCODED_DICTIONARIES as $property) {
            self::flattenProperty($old, $property);
            self::flattenProperty($new, $property);
        }
        foreach ($old as $key => $value) {
            if (array_key_exists($key, $new)) {
                if ($value === $new[$key]) {
                    $unchangedKeys[] = $key;
                }
            } else {
                $new[$key] = null;
            }
        }
        foreach ($unchangedKeys as $key) {
            unset($new[$key]);
        }

        return $new;
    }

    protected static function flattenProperty(array &$properties, $property)
    {
        // TODO: dots in varnames -> throw or escape?
        if (isset($properties[$property])) {
            foreach ((array) $properties[$property] as $key => $value) {
                $properties["$property.$key"] = $value;
            }
            unset($properties[$property]);
        }
    }
}
