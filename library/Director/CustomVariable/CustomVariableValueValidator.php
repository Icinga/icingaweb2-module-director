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
     * A datalist's item type child decides single value vs list, same as a
     * plain dynamic-array. Pass $propertyUuid and $db to check that. Without
     * them a datalist just takes either shape, only a dictionary is rejected.
     *
     * @param string $key
     * @param mixed $value
     * @param string $valueType
     * @param ?UuidInterface $propertyUuid
     * @param ?Db $db
     *
     * @throws InvalidArgumentException
     */
    public static function assertMatchesType(
        string $key,
        mixed $value,
        string $valueType,
        ?UuidInterface $propertyUuid = null,
        ?Db $db = null
    ): void {
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
            case 'datalist-strict':
            case 'datalist-non-strict':
                if ($propertyUuid !== null && $db !== null) {
                    self::assertDatalistShapeMatchesItemType($key, $value, $propertyUuid, $db);
                    break;
                }

                // No property to check the item type against, fall back to the
                // loose check, any single value or list is fine here.
                if (($value instanceof stdClass) || (is_array($value) && ! array_is_list($value))) {
                    throw new InvalidArgumentException(sprintf(
                        "The custom variable '%s' expects a single value or a list of values, got %s",
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
     * Reject a value whose shape doesn't match the datalist's own item type child
     *
     * @throws InvalidArgumentException
     */
    private static function assertDatalistShapeMatchesItemType(
        string $key,
        mixed $value,
        UuidInterface $propertyUuid,
        Db $db
    ): void {
        if (self::datalistAcceptsArray($propertyUuid, $db)) {
            if (! is_array($value) || ! array_is_list($value)) {
                throw new InvalidArgumentException(sprintf(
                    "The custom variable '%s' expects a list of values, got %s",
                    $key,
                    get_debug_type($value)
                ));
            }

            return;
        }

        if (is_array($value) || $value instanceof stdClass) {
            throw new InvalidArgumentException(sprintf(
                "The custom variable '%s' expects a single value, got %s",
                $key,
                get_debug_type($value)
            ));
        }
    }

    /**
     * Whether the datalist's item type child says it holds a list, not a single value
     */
    private static function datalistAcceptsArray(UuidInterface $propertyUuid, Db $db): bool
    {
        $property = DirectorProperty::loadWithUniqueId($propertyUuid, $db);
        if ($property === null) {
            return false;
        }

        foreach ($property->fetchItemsFromDb() as $child) {
            if ($child->get('value_type') === 'dynamic-array') {
                return true;
            }
        }

        return false;
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

        $allowedNames = [];
        foreach ($datalist->getEntries() as $entry) {
            $allowedNames[] = $entry->get('entry_name');
        }

        foreach ((array) $value as $singleValue) {
            if (! in_array($singleValue, $allowedNames, true)) {
                throw new InvalidArgumentException(sprintf(
                    "'%s' is not a valid value for the custom variable '%s'",
                    (string) $singleValue,
                    $key
                ));
            }
        }
    }
}
