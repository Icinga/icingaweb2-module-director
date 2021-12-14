<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

abstract class IcingaRanges
{
    /** @var IcingaTimePeriodRange[]|IcingaScheduledDowntimeRange[] */
    protected $storedRanges = [];

    /** @var IcingaTimePeriodRange[]|IcingaScheduledDowntimeRange[] */
    protected $ranges = [];

    protected $modified = false;

    protected $object;

    private $position = 0;

    protected $idx = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->ranges);
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->ranges[$this->idx[$this->position]];
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->idx[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->position;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return array_key_exists($this->position, $this->idx);
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->ranges)) {
            return $this->ranges[$key];
        }

        return null;
    }

    public function getValues()
    {
        $res = array();
        foreach ($this->ranges as $key => $range) {
            $res[$key] = $range->range_value;
        }

        return (object) $res;
    }

    public function getOriginalValues()
    {
        $res = array();
        foreach ($this->storedRanges as $key => $range) {
            $res[$key] = $range->range_value;
        }

        return (object) $res;
    }

    public function getRanges()
    {
        return $this->ranges;
    }

    protected function modify($range, $value)
    {
        $this->ranges[$range]->range_key = $value;
    }

    public function set($ranges)
    {
        foreach ($ranges as $range => $value) {
            $this->setRange($range, $value);
        }

        $toDelete = array_diff(array_keys($this->ranges), array_keys($ranges));
        foreach ($toDelete as $range) {
            $this->remove($range);
        }

        return $this;
    }

    public function setRange($range, $value)
    {
        if ($value === null && array_key_exists($range, $this->ranges)) {
            $this->remove($range);
            return $this;
        }

        if (array_key_exists($range, $this->ranges)) {
            if ($this->ranges[$range]->range_value === $value) {
                return $this;
            } else {
                $this->ranges[$range]->range_value = $value;
                $this->modified = true;
            }
        } else {
            $class = $this->getRangeClass();
            $this->ranges[$range] = $class::create([
                $this->objectIdColumn => $this->object->id,
                'range_key'   => $range,
                'range_value' => $value,
            ]);
            $this->modified = true;
        }

        return $this;
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($range)
    {
        return array_key_exists($range, $this->ranges);
    }

    public function remove($range)
    {
        if (array_key_exists($range, $this->ranges)) {
            unset($this->ranges[$range]);
        }

        $this->modified = true;
        $this->refreshIndex();
    }

    public function clear()
    {
        $this->ranges = [];
        $this->modified = true;
        $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        ksort($this->ranges);
        $this->idx = array_keys($this->ranges);
    }

    public function listRangesNames()
    {
        return array_keys($this->ranges);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    public function getRangeTableName()
    {
        return $this->object->getTableName() . '_range';
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();
        $connection = $this->object->getConnection();

        $table = $this->getRangeTableName();

        $query = $db->select()
            ->from(['o' => $table])
            ->where('o.' . $this->objectIdColumn . ' = ?', (int) $this->object->get('id'))
            ->order('o.range_key');

        $class = $this->getRangeClass();
        $this->ranges = $class::loadAll($connection, $query, 'range_key');
        $this->setBeingLoadedFromDb();

        return $this;
    }

    public function setBeingLoadedFromDb()
    {
        $this->storedRanges = [];

        foreach ($this->ranges as $key => $range) {
            $range->setBeingLoadedFromDb();
            $this->storedRanges[$key] = clone($range);
        }
        $this->refreshIndex();
        $this->modified = false;
    }

    public function store()
    {
        $db = $this->object->getConnection();
        if (! $this->hasBeenModified()) {
            return false;
        }

        $table = $this->getRangeTableName();
        $objectId = (int) $this->object->get('id');
        $idColumn = $this->objectIdColumn;
        foreach ($this->ranges as $range) {
            if ($range->hasBeenModified()) {
                $range->setConnection($db);
                if ((int) $range->get($idColumn) !== $objectId) {
                    $range->set($idColumn, $objectId);
                }
            }
        }

        foreach (array_diff(array_keys($this->storedRanges), array_keys($this->ranges)) as $delete) {
            $range = $this->storedRanges[$delete];
            $range->setConnection($db);
            $range->set($idColumn, $objectId);
            $db->getDbAdapter()->delete($table, $range->createWhere());
            unset($this->ranges[$delete]);
        }
        foreach ($this->ranges as $range) {
            $range->store();
        }
        $this->setBeingLoadedFromDb();

        return true;
    }

    /**
     * @return IcingaTimePeriodRange|IcingaScheduledDowntimeRange|string IDE hint
     */
    protected function getRangeClass()
    {
        return $this->rangeClass;
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $ranges = new static($object);
        return $ranges->loadFromDb();
    }

    public function toConfigString()
    {
        if (empty($this->ranges) && $this->object->object_type === 'template') {
            return '';
        }

        $string = "    ranges = {\n";

        foreach ($this->ranges as $range) {
            $string .= sprintf(
                "        %s\t= %s\n",
                c::renderString($range->range_key),
                c::renderString($range->range_value)
            );
        }

        return $string . "    }\n";
    }

    abstract public function toLegacyConfigString();

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(
                function () {
                }
            );
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }
}
