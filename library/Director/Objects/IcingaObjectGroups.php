<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaObjectGroups implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedGroups = array();

    protected $groups = array();

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
        return count($this->groups);
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
        if (array_key_exists($key, $this->groups)) {
            return $this->groups[$key];
        }

        return null;
    }

    public function set($group)
    {
        $existing = array_keys($this->groups);
        $new = array();
        $class = $this->getGroupClass();
        foreach ($group as $g) {

            if ($g instanceof $class) {
                $new[] = $g->object_name;
            } else {
                $new[] = $g;
            }
        }
        sort($existing);
        sort($new);
        if ($existing === $new) {
            return $this;
        }

        $this->groups = array();
        return $this->add($group);
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

    public function remove($group)
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

    public function add($group)
    {
        // TODO: only one query when adding array
        if (is_array($group)) {
            foreach ($group as $g) {
                $this->add($g);
            }
            return $this;
        }

        if (array_key_exists($group, $this->groups)) {
            return $this;
        }

        $class = $this->getGroupClass();
        $connection = $this->object->getConnection();

        if ($group instanceof $class) {
            $this->groups[$group->object_name] = $group;
        } elseif (is_string($group)) {
            $query = $this->object->getDb()->select()->from(
                $this->getGroupTableName()
            )->where('object_name = ?', $group);
            $groups = $class::loadAll($connection, $query, 'object_name');
        }
        if (! array_key_exists($group, $groups)) {
            throw new ProgrammingError(
                'The group "%s" doesn\'t exists.',
                $group
            );
        }

        $this->groups[$group] = $groups[$group];

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function getGroupTableName()
    {
        return $this->object->getTableName() . 'group';
    }


    protected function getGroupMemberTableName()
    {
        return $this->object->getTableName() . 'group_' . $this->getType();
    }

    public function listGroupNames()
    {
        return array_keys($this->groups);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();
        $connection = $this->object->getConnection();

        $type = $this->getType();

        $table = $this->object->getTableName();
        $query = $db->select()->from(
            array('o' => $table),
            array()
        )->join(
            array('go' => $table . 'group_' . $type),
            'go.' . $type . '_id = o.id',
            array()
        )->join(
            array('g' => $table . 'group'),
            'go.' . $type . 'group_id = g.id',
            '*'
        )->where('o.object_name = ?', $this->object->object_name)
        ->order('g.object_name');

        $class = $this->getGroupClass();
        $this->groups = $class::loadAll($connection, $query, 'object_name');
        $this->storedGroups = $this->groups;

        return $this;
    }

    public function store()
    {
        $storedGroups = array_keys($this->storedGroups);
        $groups = array_keys($this->groups);

        $objectId = $this->object->id;
        $type = $this->getType();

        $objectCol = $type . '_id';
        $groupCol = $type . 'group_id';

        $toDelete = array_diff($storedGroups, $groups);
        foreach ($toDelete as $group) {
            $where = sprintf(
                $objectCol . ' = %d AND ' . $groupCol . ' = %d',
                $objectId,
                $this->storedGroups[$group]->id
            );

            $this->object->db->delete(
                $this->getGroupMemberTableName(),
                $where
            );
        }

        $toAdd = array_diff($groups, $storedGroups);
        foreach ($toAdd as $group) {
            $this->object->db->insert(
                $this->getGroupMemberTableName(),
                array(
                    $objectCol => $objectId,
                    $groupCol => $this->groups[$group]->id
                )
            );
        }
        $this->storedGroups = $this->groups;

        return true;
    }

    protected function getGroupClass()
    {
        return __NAMESPACE__ . '\\Icinga' .ucfirst($this->object->getShortTableName()) . 'Group';
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $groups = new static($object);
        return $groups->loadFromDb();
    }

    public function toConfigString()
    {
        $groups = array_keys($this->groups);

        if (empty($groups)) {
            return '';
        }

        return c::renderKeyValue('groups', c::renderArray($groups));
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
