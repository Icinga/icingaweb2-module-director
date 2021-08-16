<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\IcingaObject;

class IcingaObjectModification
{
    /**
     * @param DbObject $object
     * @return ObjectModification
     */
    public static function getModification(DbObject $object)
    {
        if ($object->shouldBeRemoved()) {
            return static::delete($object);
        }

        if ($object->hasBeenLoadedFromDb()) {
            return static::modify($object);
        }

        return static::create($object);
    }

    protected static function fixForeignKeys($object)
    {
        // TODO: Generic, _name?? Lookup?
        $keys = [
            'check_command_name',
            'check_period_name',
            'event_command_name',
            'command_endpoint_name',
            'zone_name',
            'host_name',
        ];

        foreach ($keys as $key) {
            if (property_exists($object, $key)) {
                $object->{substr($key, 0, -5)} = $object->$key;
                unset($object->$key);
            }
        }
    }

    public static function applyModification(ObjectModification $modification, DbObject $object = null)
    {
        if ($modification->isDeletion()) {
            $object->markForRemoval();
        } elseif ($modification->isCreation()) {
            /** @var string|DbObject $class */
            $class = $modification->getClassName();
            $properties = $modification->getProperties()->jsonSerialize();
            self::fixForeignKeys($properties);
            $object = $class::create((array) $properties);
        } else {
            // TODO: Add "reset Properties", those that have been nulled
            $properties = (array) $modification->getProperties()->jsonSerialize();
            foreach (['vars', 'arguments'] as $property) { // TODO: define in one place, see BranchModificationStore
                self::flattenProperty($properties, $property);
            }
            if ($object === null) {
                echo '<pre>';
                debug_print_backtrace();
                echo '</pre>';
                exit;
            }
            foreach ($properties as $key => $value) {
                $object->set($key, $value);
            }
        }

        return $object;
    }

    public static function delete(DbObject $object)
    {
        return ObjectModification::delete(
            get_class($object),
            self::getKey($object),
            $object->toPlainObject(false, true)
        );
    }

    public static function create(DbObject $object)
    {
        return ObjectModification::create(
            get_class($object),
            self::getKey($object),
            $object->toPlainObject(false, true)
        );
    }

    protected static function getKey(DbObject $object)
    {
        return $object->getKeyParams();
    }

    protected static function flattenProperty(array &$properties, $property)
    {
        // TODO: dots in varnames -> throw or escape?
        if (isset($properties[$property])) {
            foreach ($properties[$property] as $key => $value) {
                $properties["$property.$key"] = $value;
            }
            unset($properties[$property]);
        }
    }

    public static function modify(DbObject $object)
    {
        if (! $object instanceof IcingaObject) {
            throw new ProgrammingError('Plain object helpers for DbObject must be implemented');
        }
        $old = (array) $object->getPlainUnmodifiedObject();
        $new = (array) $object->toPlainObject(false, true);
        $unchangedKeys = [];
        self::flattenProperty($old, 'vars');
        self::flattenProperty($old, 'arguments');
        self::flattenProperty($new, 'vars');
        self::flattenProperty($new, 'arguments');
        foreach ($old as $key => $value) {
            if (array_key_exists($key, $new) && $value === $new[$key]) {
                $unchangedKeys[] = $key;
            }
        }
        foreach ($unchangedKeys as $key) {
            unset($old[$key]);
            unset($new[$key]);
        }

        return ObjectModification::modify(get_class($object), self::getKey($object), $old, $new);
    }
}
