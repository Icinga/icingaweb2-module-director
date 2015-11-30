<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaCommandArgument;

class IcingaArguments implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedArguments = array();

    protected $arguments = array();

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
        return count($this->arguments);
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

        return $this->groups[$this->idx[$this->position]];
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
        if (array_key_exists($key, $this->arguments)) {
            return $this->arguments[$key];
        }

        return null;
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($group)
    {
        return array_key_exists($group, $this->groups);
    }

    public function remove($argument)
    {
        if (array_key_exists($group, $this->groups)) {
            unset($this->groups[$group]);
        }

        $this->modified = true;
        $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        ksort($this->groups);
        $this->idx = array_keys($this->groups);
    }

    public function add(IcingaCommandArgument $argument)
    {
        if (array_key_exists($argument->argument_name, $this->arguments)) {
            // TODO: Fail unless $argument equals existing one
            return $this;
        }

        $this->arguments[$argument->argument_name] = $argument;
        $connection = $this->object->getConnection();
        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function getGroupTableName()
    {
        return $this->object->getTableName() . 'group';
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();
        $connection = $this->object->getConnection();

        $table = $this->object->getTableName();
        $query = $db->select()->from(
            array('o' => $table),
            array()
        )->join(
            array('a' => 'icinga_command_argument'),
            'o.id = a.command_id',
            '*'
        )->where('o.object_name = ?', $this->object->object_name)
        ->order('a.sort_order')->order('a.argument_name');

        $this->arguments = IcingaCommandArgument::loadAll($connection, $query, 'id');
        $this->cloneStored();

        return $this;
    }

    protected function cloneStored()
    {
        $this->storedArguments = array();
        foreach ($this->arguments as $k => $v) {
            $this->storedArguments[$k] = clone($v);
        }
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $arguments = new static($object);
        return $arguments->loadFromDb();
    }

    public function toConfigString()
    {
        if (empty($this->arguments)) {
            return '';
        }

        $args = array();
        foreach ($this->arguments as $arg) {
            $args[$arg->argument_name] = $arg->toConfigString();
        }
        return c::renderKeyValue('arguments', c::renderDictionary($args));
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(function () {});
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
