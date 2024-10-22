<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Json;

use function in_array;

/**
 * Hardcoded branch-related settings
 */
class BranchSettings
{
    // TODO: Ranges is weird. key = scheduled_downtime_id, range_type, range_key
    const ENCODED_ARRAYS = ['imports', 'groups', 'ranges', 'users', 'usergroups'];

    const ENCODED_DICTIONARIES = ['vars', 'arguments'];

    const BRANCH_SPECIFIC_PROPERTIES = [
        'uuid',
        'branch_uuid',
        'branch_created',
        'branch_deleted',
        'set_null',
    ];

    const BRANCH_BOOLEANS = [
        'branch_created',
        'branch_deleted',
    ];

    const RELATED_SETS = [
        'types',
        'states',
    ];

    public static function propertyIsEncodedArray($property)
    {
        return in_array($property, self::ENCODED_ARRAYS, true);
    }

    public static function propertyIsRelatedSet($property)
    {
        // TODO: get from object class
        return in_array($property, self::RELATED_SETS, true);
    }

    public static function propertyIsEncodedDictionary($property)
    {
        return in_array($property, self::ENCODED_DICTIONARIES, true);
    }

    public static function propertyIsBranchSpecific($property)
    {
        return in_array($property, self::BRANCH_SPECIFIC_PROPERTIES, true);
    }

    public static function flattenEncodedDicationaries(array &$properties)
    {
        foreach (self::ENCODED_DICTIONARIES as $property) {
            self::flattenProperty($properties, $property);
        }
    }

    public static function normalizeBranchedObjectFromDb($row)
    {
        $normalized = [];
        $row = (array) $row;
        foreach ($row as $key => $value) {
            if (! static::propertyIsBranchSpecific($key)) {
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
                if ($value !== null && static::propertyIsEncodedArray($key)) {
                    $value = Json::decode($value);
                }
                if ($value !== null && static::propertyIsRelatedSet($key)) {
                    // TODO: We might want to combine them (current VS branched)
                    $value = Json::decode($value);
                }
                if ($value !== null && static::propertyIsEncodedDictionary($key)) {
                    $value = Json::decode($value);
                }
                if ($value !== null) {
                    $normalized[$key] = $value;
                }
            }
        }
        static::flattenEncodedDicationaries($row);
        if (isset($row['set_null'])) {
            foreach (Json::decode($row['set_null']) as $property) {
                $normalized[$property] = null;
            }
        }
        foreach (self::BRANCH_BOOLEANS as $key) {
            if ($row[$key] === 'y') {
                $row[$key] = true;
            } elseif ($row[$key] === 'n') {
                $row[$key] = false;
            } else {
                throw new \RuntimeException(sprintf(
                    "Boolean DB property expected, got '%s' for '%s'",
                    $row[$key],
                    $key
                ));
            }
        }

        return $normalized;
    }

    public static function flattenProperty(array &$properties, $property)
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
