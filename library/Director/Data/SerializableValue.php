<?php

namespace Icinga\Module\Director\Data;

use InvalidArgumentException;
use JsonSerializable;
use stdClass;

use function get_class;
use function gettype;
use function is_array;
use function is_object;
use function is_scalar;

class SerializableValue implements Serializable
{
    protected $value = [];

    /**
     * @param stdClass|array $object
     * @return static
     */
    public static function fromSerialization($value)
    {
        $self = new static();
        static::assertSerializableValue($value);
        $self->value = $value;

        return $self;
    }

    public static function wantSerializable($value)
    {
        if ($value instanceof SerializableValue) {
            return $value;
        }

        return static::fromSerialization($value);
    }

    /**
     * TODO: Check whether json_encode() is faster
     *
     * @param mixed $value
     * @return bool
     */
    protected static function assertSerializableValue($value)
    {
        if ($value === null || is_scalar($value)) {
            return true;
        }
        if (is_object($value)) {
            if ($value instanceof JsonSerializable) {
                return true;
            }

            if ($value instanceof stdClass) {
                foreach ((array) $value as $val) {
                    static::assertSerializableValue($val);
                }

                return true;
            }
        }

        if (is_array($value)) {
            foreach ($value as $val) {
                static::assertSerializableValue($val);
            }

            return true;
        }

        throw new InvalidArgumentException('Serializable value expected, got ' . static::getPhpType($value));
    }

    protected static function getPhpType($var)
    {
        if (is_object($var)) {
            return get_class($var);
        }

        return gettype($var);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->value;
    }
}
