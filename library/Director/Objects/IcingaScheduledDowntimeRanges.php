<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaScheduledDowntimeRanges implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedRanges = array();

    protected $ranges = array();

    protected $modified = false;

    protected $object;

    private $position = 0;

    protected $idx = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    public function count()
    {
        return count($this->ranges);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->ranges[$this->idx[$this->position]];
    }

    public function key()
    {
        return $this->idx[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

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
            $this->ranges[$range] = IcingaTimePeriodRange::create(array(
                'timeperiod_id' => $this->object->id,
                'range_key'     => $range,
                'range_value'   => $value,
            ));
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
        $this->ranges = array();
        $this->modified = true;
        $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        ksort($this->ranges);
        $this->idx = array_keys($this->ranges);
    }

    protected function getRangeClass()
    {
        return __NAMESPACE__ . '\\Icinga' .ucfirst($this->object->getShortTableName()) . 'Range';
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

        $query = $db->select()->from(
            array('o' => $table)
        )->where('o.timeperiod_id = ?', (int) $this->object->id)
            ->order('o.range_key');

        $class = $this->getClass();
        $this->ranges = $class::loadAll($connection, $query, 'range_key');
        $this->storedRanges = array();

        foreach ($this->ranges as $key => $range) {
            $this->storedRanges[$key] = clone($range);
        }

        return $this;
    }

    public function store()
    {
        foreach ($this->ranges as $range) {
            $range->timeperiod_id = $this->object->id;
            $range->store($this->object->getConnection());
        }

        foreach (array_diff(array_keys($this->storedRanges), array_keys($this->ranges)) as $delete) {
            $this->storedRanges[$delete]->delete();
        }

        $this->storedRanges = $this->ranges;

        return true;
    }

    protected function getClass()
    {
        return __NAMESPACE__ . '\\IcingaTimePeriodRange';
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
