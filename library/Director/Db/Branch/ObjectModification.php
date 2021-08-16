<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\DataArrayHelper;
use Icinga\Module\Director\Data\Serializable;
use Icinga\Module\Director\Data\SerializableValue;
use InvalidArgumentException;

class ObjectModification implements Serializable
{
    const ACTION_CREATE = 'create';
    const ACTION_MODIFY = 'modify';
    const ACTION_DELETE = 'delete';

    protected static $serializationProperties = [
        'class',
        'key',
        'action',
        'properties',
        'formerProperties',
    ];

    /** @var string */
    protected $class;

    /** @var \stdClass */
    protected $key;

    /** @var string */
    protected $action;

    /** @var SerializableValue|null */
    protected $properties;

    /** @var SerializableValue|null */
    protected $formerProperties;

    public function __construct(
        $class,
        $key,
        $action,
        SerializableValue $properties = null,
        SerializableValue $formerProperties = null
    ) {
        $this->class = $class;
        $this->key = $key;
        $this->assertValidAction($action);
        $this->action = $action;
        $this->properties = $properties;
        $this->formerProperties = $formerProperties;
    }

    public static function delete($class, $key, $formerProperties)
    {
        return new static(
            $class,
            $key,
            self::ACTION_DELETE,
            null,
            SerializableValue::wantSerializable($formerProperties)
        );
    }

    public static function create($class, $key, $properties)
    {
        return new static($class, $key, self::ACTION_CREATE, SerializableValue::wantSerializable($properties));
    }

    public static function modify($class, $key, $formerProperties, $properties)
    {
        return new static(
            $class,
            $key,
            self::ACTION_MODIFY,
            SerializableValue::wantSerializable($properties),
            SerializableValue::wantSerializable($formerProperties)
        );
    }

    protected function assertValidAction($action)
    {
        if ($action !== self::ACTION_MODIFY
            && $action !== self::ACTION_CREATE
            && $action !== self::ACTION_DELETE
        ) {
            throw new InvalidArgumentException("Valid action expected, got $action");
        }
    }

    public function isDeletion()
    {
        return $this->action === self::ACTION_DELETE;
    }

    public function isCreation()
    {
        return $this->action === self::ACTION_CREATE;
    }

    public function isModification()
    {
        return $this->action === self::ACTION_MODIFY;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function jsonSerialize()
    {
        return (object) [
            'class'            => $this->class,
            'key'              => $this->key,
            'action'           => $this->action,
            'properties'       => $this->properties,
            'formerProperties' => $this->formerProperties,
        ];
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getFormerProperties()
    {
        return $this->formerProperties;
    }

    public function getClassName()
    {
        return $this->class;
    }

    public function getKeyParams()
    {
        return $this->key;
    }

    public static function fromSerialization($value)
    {
        $value = DataArrayHelper::wantArray($value);
        DataArrayHelper::failOnUnknownProperties($value, self::$serializationProperties);
        DataArrayHelper::requireProperties($value, ['class', 'key', 'action']);

        return new static(
            $value['class'],
            $value['key'],
            $value['action'],
            isset($value['properties']) ? SerializableValue::fromSerialization($value['properties']) : null,
            isset($value['formerProperties']) ? SerializableValue::fromSerialization($value['formerProperties']) : null
        );
    }
}
