<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorProperty;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use stdClass;

/**
 * Validates custom variable values submitted through the REST API against
 * the shape declared by their director_property value_type
 */
class CustomVariableValueValidator
{
    /**
     * Assert that the given value has the shape expected for value_type
     *
     * Only the structural category is checked, scalar, list or dictionary.
     * Loosely typed scalars such as a numeric string for a number property
     * are accepted, matching the looseness the config renderer already
     * allows.
     *
     * @param string $key
     * @param mixed $value
     * @param string $valueType
     *
     * @throws InvalidArgumentException
     */
    public static function assertMatchesType(string $key, mixed $value, string $valueType): void
    {
        switch ($valueType) {
            case 'fixed-array':
            case 'dynamic-array':
                if (! is_array($value) || ! array_is_list($value)) {
                    throw new InvalidArgumentException(sprintf(
                        "The custom variable '%s' expects a list of values, got %s",
                        $key,
                        get_debug_type($value)
                    ));
                }

                break;
            case 'fixed-dictionary':
            case 'dynamic-dictionary':
                if (! $value instanceof stdClass) {
                    throw new InvalidArgumentException(sprintf(
                        "The custom variable '%s' expects a dictionary of values, got %s",
                        $key,
                        get_debug_type($value)
                    ));
                }

                break;
            default:
                if (is_array($value) || $value instanceof stdClass) {
                    throw new InvalidArgumentException(sprintf(
                        "The custom variable '%s' expects a single value, got %s",
                        $key,
                        get_debug_type($value)
                    ));
                }
        }
    }

    /**
     * Assert that the given value is one of the entries of the datalist
     * linked to the property with the given uuid
     *
     * A property without a linked datalist is treated as unrestricted.
     *
     * @param string $key
     * @param mixed $value
     * @param UuidInterface $propertyUuid
     * @param Db $db
     *
     * @throws InvalidArgumentException
     */
    public static function assertDatalistValueAllowed(
        string $key,
        mixed $value,
        UuidInterface $propertyUuid,
        Db $db
    ): void {
        $property = DirectorProperty::loadWithUniqueId($propertyUuid, $db);
        if ($property === null) {
            return;
        }

        $datalist = $property->getDatalist();
        if ($datalist === null) {
            return;
        }

        foreach ($datalist->getEntries() as $entry) {
            if ($entry->get('entry_name') === $value) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            "'%s' is not a valid value for the custom variable '%s'",
            (string) $value,
            $key
        ));
    }
}
