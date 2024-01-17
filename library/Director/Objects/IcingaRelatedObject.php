<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaObject;

/**
 * Related Object
 *
 * This class comes in handy when working with simple foreign key references. In
 * contrast to an ORM it helps to deal with lazy-loaded objects in a way allowing
 * us to render objects with references which to no longer (or not yet) exist
 */
class IcingaRelatedObject
{
    /** @var IcingaObject Main object with (optional) relation */
    protected $owner;

    /** @var int Related object id */
    protected $id;

    /** @var int Related object name */
    protected $name;

    /** @var int Relation property name, e.g. 'host' */
    protected $key;

    /** @var int Relation property, e.g. 'host_id' */
    protected $idKey;

    /** @var IcingaObject Related object once loaded */
    protected $object;

    /** @var string Related class name */
    protected $className;

    /**
     * IcingaRelatedObject constructor
     *
     * @param IcingaObject $owner Main object referring a related one
     * @param string       $key   Main objects (short) property name for this
     */
    public function __construct(IcingaObject $owner, $key)
    {
        $this->owner = $owner;
        $this->key   = $key;
        $this->idKey = $key . '_id';
    }

    /**
     * Set a specific id
     *
     * @param $id int
     *
     * @return self
     */
    public function setId($id)
    {
        if (! is_int($id)) {
            throw new ProgrammingError(
                'An id must be an integer'
            );
        }

        if ($this->object !== null) {
            if ($this->object->id === $id) {
                return $this;
            } else {
                $this->object = null;
            }
        }

        if ($this->object === null) {
            $this->name = null;
        }

        $this->id = $id;
        $this->owner->set($this->getRealPropertyName(), $id);

        return $this;
    }

    /**
     * Return the related objects id
     *
     * @return int
     */
    public function getId()
    {
        if ($this->id === null) {
            $this->id = $this->getObject()->id;
        }

        return $this->id;
    }

    /**
     * Lazy-load the related object
     *
     * @return IcingaObject
     */
    public function getObject()
    {
        // TODO: This is unfinished

        if ($this->object === null) {
            $class = $this->getClassName();

            if ($this->name === null) {
                if ($id = $this->getId()) {
                }
            } else {
                $this->object = $class::load($this->name, $this->owner->getConnection());
            }
        }
        return $this->object;
    }

    /**
     * The real property name pointing to this relation, e.g. 'host_id'
     *
     * @return string
     */
    public function getRealPropertyName()
    {
        return $this->key . '_id';
    }

    /**
     * Full related class name
     *
     * @return string
     */
    public function getClassName()
    {
        if ($this->className === null) {
            $this->className = __NAMESPACE__ . '\\' . $this->getShortClassName();
        }

        return $this->className;
    }

    /**
     * Related class name relative to Icinga\Module\Director\Objects
     *
     * @return string
     */
    public function getShortClassName()
    {
        return $this->owner->getRelationObjectClass($this->key);
    }

    /**
     * Set a related property
     *
     * This might be a string or an object
     * @param $related string|IcingaObject
     * @throws ProgrammingError
     *
     * return self
     */
    public function set($related)
    {
        if (is_string($related)) {
            $this->name = $related;
        } elseif (is_object($related)) {
            $className = $this->getClassName();
            if ($related instanceof $className) {
                $this->object = $related;
                $this->name = $object->object_name;
                $this->id = $object->id;
            } else {
                throw new ProgrammingError(
                    'Trying to set a related "%s" while expecting "%s"',
                    get_class($related),
                    $this->getShortClassName()
                );
            }
        } else {
            throw new ProgrammingError(
                'Related object can be name or object, got: %s',
                var_export($related, true)
            );
        }

        return $this;
    }

    /**
     * Get the name of the related object
     *
     * @return string
     */
    public function getName()
    {
        if ($this->name === null) {
            return $this->owner->{$this->key};
        } else {
            return $this->name;
        }
    }

    /**
     * Conservative constructor to avoid issued with PHP GC
     */
    public function __destruct()
    {
        unset($this->owner);
    }
}
