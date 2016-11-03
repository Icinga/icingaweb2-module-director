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

        return $this->arguments[$this->idx[$this->position]];
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
            if ($this->arguments[$key]->shouldBeRemoved()) {
                return null;
            }

            return $this->arguments[$key];
        }

        return null;
    }

    public function set($key, $value)
    {
        if ($value instanceof IcingaCommandArgument) {
            $argument = $value;
        } else {
            $argument = IcingaCommandArgument::create(
                $this->mungeCommandArgument($key, $value)
            );
        }

        $argument->set('command_id', $this->object->id);

        $key = $argument->argument_name;
        if (array_key_exists($key, $this->arguments)) {
            $this->arguments[$key]->replaceWith($argument);
            if ($this->arguments[$key]->hasBeenModified()) {
                $this->modified = true;
            }
        } elseif (array_key_exists($key, $this->storedArguments)) {
            $this->arguments[$key] = clone($this->storedArguments[$key]);
            $this->arguments[$key]->replaceWith($argument);
            if ($this->arguments[$key]->hasBeenModified()) {
                $this->modified = true;
            }
        } else {
            $this->add($argument);
            $this->modified = true;
        }

        return $this;
    }

    protected function mungeCommandArgument($key, $value)
    {
        $attrs = array(
            'argument_name' => (string) $key,
        );

        $map = array(
            'skip_key'    => 'skip_key',
            'repeat_key'  => 'repeat_key',
            'required'    => 'required',
            // 'order'       => 'sort_order',
            'description' => 'description',
            'set_if'      => 'set_if',
        );

        $argValue = null;
        if (is_object($value)) {
            if (property_exists($value, 'order')) {
                $attrs['sort_order'] = (string) $value->order;
            }

            foreach ($map as $apiKey => $dbKey) {
                if (property_exists($value, $apiKey)) {
                    $attrs[$dbKey] = $value->$apiKey;
                }
            }
            if (property_exists($value, 'type')) {
                if ($value->type === 'Function') {
                    $attrs['argument_value'] = '/* Unable to fetch function body through API */';
                    $attrs['argument_format'] = 'expression';
                }
            } elseif (property_exists($value, 'value')) {
                if (is_object($value->value)) {
                    if ($value->value->type === 'Function') {
                        $attrs['argument_value'] = '/* Unable to fetch function body through API */';
                        $attrs['argument_format'] = 'expression';
                    } else {
                        die('Unable to resolve command argument');
                    }
                } else {
                    $argValue = $value->value;
                    if (is_string($argValue)) {
                        $attrs['argument_value'] = $argValue;
                        $attrs['argument_format'] = 'string';
                    } else {
                        $attrs['argument_value'] = $argValue;
                        $attrs['argument_format'] = 'json';
                    }
                }
            }
        } else {
            if (is_string($value)) {
                $attrs['argument_value'] = $value;
                $attrs['argument_format'] = 'string';
            } else {
                $attrs['argument_value'] = $value;
                $attrs['argument_format'] = 'json';
            }
        }

        if (array_key_exists('set_if', $attrs) && is_object($attrs['set_if'])) {
            if ($attrs['set_if']->type === 'Function') {
                $attrs['set_if'] = '/* Unable to fetch function body through API */';
                $attrs['set_if_format'] = 'expression';
            }
        }

        return $attrs;
    }

    // TODO -> UNFINISHED!!!
    public function setArguments($arguments)
    {
        if (empty($arguments)) {
            if (count($this->arguments)) {
                $this->arguments = array();
                $this->modified = true;
            }

            return $this;
        }

        $arguments = (array) $arguments;

        foreach ($arguments as $arg => $val) {
            $this->set($arg, $val);
        }

        foreach (array_diff(
            array_keys($this->arguments),
            array_keys($arguments)
        ) as $arg) {
            if ($this->arguments[$arg]->hasBeenLoadedFromDb()) {
                $this->arguments[$arg]->markForRemoval();
                $this->modified = true;
            } else {
                unset($this->arguments[$arg]);
            }
        }

        return $this;
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($argument)
    {
        return array_key_exists($argument, $this->arguments);
    }

    public function remove($argument)
    {
        if (array_key_exists($argument, $this->arguments)) {
            $this->arguments[$argument]->markForRemoval();
            $this->modified = true;
            $this->refreshIndex();
        }

        return $this;
    }

    protected function refreshIndex()
    {
        ksort($this->arguments);
        $this->idx = array_keys($this->arguments);
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

        $this->arguments = IcingaCommandArgument::loadAll($connection, $query, 'argument_name');
        $this->cloneStored();
        $this->refreshIndex();

        return $this;
    }

    public function toPlainObject(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null,
        $resolveIds = true
    ) {
        $args = array();
        foreach ($this->arguments as $arg) {
            if ($arg->shouldBeRemoved()) {
                continue;
            }

            $args[$arg->argument_name] = $arg->toPlainObject(
                $resolved,
                $skipDefaults,
                null,
                $resolveIds
            );
        }

        return $args;
    }

    public function toUnmodifiedPlainObject()
    {
        $args = array();
        foreach ($this->storedArguments as $key => $arg) {
            $args[$arg->argument_name] = $arg->toPlainObject();
        }

        return $args;
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

    public function store()
    {
        $db = $this->object->getConnection();

        $dummy = IcingaCommandArgument::create();

        $deleted = array();
        foreach ($this->arguments as $key => $argument) {
            if ($argument->shouldBeRemoved()) {
                $deleted[] = $key;
            } else {
                $argument->command_id = $this->object->id;
                $argument->store($db);
            }
        }

        foreach ($deleted as $key) {
            $this->arguments[$key]->delete();
            unset($this->arguments[$key]);
        }

        $this->cloneStored();
        return $this;
    }

    public function toConfigString()
    {
        if (empty($this->arguments)) {
            return '';
        }

        $args = array();
        foreach ($this->arguments as $arg) {
            if ($arg->shouldBeRemoved()) {
                continue;
            }

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

    public function toLegacyConfigString()
    {
        return 'UNSUPPORTED';
    }
}
