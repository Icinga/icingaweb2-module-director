<?php

namespace Icinga\Module\Director\Test;

use Icinga\Module\Director\Objects\IcingaObject;

/**
 * Icinga Object test helper class
 */
abstract class IcingaObjectTestCase extends BaseTestCase
{
    protected $table;
    protected $testObjectName = '___TEST___';

    /** @var IcingaObject */
    protected $subject = null;

    protected $createdObjects = array();

    /**
     * Creates a fresh object to play with and prepares for tearDown()
     *
     * @param  string $type        table to load from
     * @param  string $object_name of the object
     * @param  array  $properties
     * @param  bool   $storeIt
     *
     * @return IcingaObject
     */
    protected function createObject($object_name, $type = null, $properties = array(), $storeIt = true)
    {
        if ($type === null) {
            $type = $this->table;
        }
        $properties['object_name'] = '___TEST___' . $type . '_' . $object_name;
        $obj = IcingaObject::createByType($type, $properties, $this->getDb());

        if ($storeIt === true) {
            $obj->store();
            $this->prepareObjectTearDown($obj);
        }

        return $obj;
    }

    /**
     * Helper method for loading an object
     *
     * @param  string  $name
     * @param null $type
     * @return IcingaObject
     */
    protected function loadObject($name, $type = null)
    {
        if ($type === null) {
            $type = $this->table;
        }
        $realName = '___TEST___' . $type . '_' . $name;
        return IcingaObject::loadByType($type, $realName, $this->getDb());
    }

    /**
     * Store the object in a list for deletion on tearDown()
     *
     * @param IcingaObject $object
     *
     * @return $this
     */
    protected function prepareObjectTearDown(IcingaObject $object)
    {
        $this->assertTrue($object->hasBeenLoadedFromDb());
        $this->createdObjects[] = $object;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function tearDown(): void
    {
        if ($this->hasDb()) {
            /** @var IcingaObject $object */
            foreach (array_reverse($this->createdObjects) as $object) {
                $object->delete();
            }

            if ($this->subject !== null) {
                $this->subject->delete();
            }
        }
    }
}
