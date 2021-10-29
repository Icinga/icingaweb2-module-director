<?php

namespace Icinga\Module\Director\Objects;

use Countable;
use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Iterator;
use RuntimeException;

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

        if (! $object->hasBeenLoadedFromDb() && PrefetchCache::shouldBeUsed()) {
            /** @var IcingaObjectGroup $class */
            $class = $this->getGroupClass();
            $class::prefetchAll($this->object->getConnection());
        }
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

    /**
     * @param $group
     * @return $this
     * @throws NotFoundError
     */
    public function set($group)
    {
        if (! is_array($group)) {
            $group = array($group);
        }

        $existing = array_keys($this->groups);
        $new = array();
        $class = $this->getGroupClass();
        $unset = array();

        foreach ($group as $k => $g) {
            if ($g instanceof $class) {
                $new[] = $g->object_name;
            } else {
                if (empty($g)) {
                    $unset[] = $k;
                    continue;
                }

                $new[] = $g;
            }
        }

        foreach ($unset as $k) {
            unset($group[$k]);
        }

        sort($existing);
        sort($new);
        if ($existing === $new) {
            return $this;
        }

        $this->groups = array();
        if (empty($group)) {
            $this->modified = true;
            $this->refreshIndex();
            return $this;
        }

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

    /**
     * @param $group
     * @param string $onError
     * @return $this
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function add($group, $onError = 'fail')
    {
        // TODO: only one query when adding array
        if (is_array($group)) {
            foreach ($group as $g) {
                $this->add($g, $onError);
            }
            return $this;
        }

        /** @var IcingaObjectGroup $class */
        $class = $this->getGroupClass();

        if ($group instanceof $class) {
            if (array_key_exists($group->getObjectName(), $this->groups)) {
                return $this;
            }

            $this->groups[$group->object_name] = $group;
        } elseif (is_string($group)) {
            if (array_key_exists($group, $this->groups)) {
                return $this;
            }

            $connection = $this->object->getConnection();

            try {
                $this->groups[$group] = $class::load($group, $connection);
            } catch (NotFoundError $e) {
                switch ($onError) {
                    case 'autocreate':
                        $newGroup = $class::create(array(
                            'object_type' => 'object',
                            'object_name' => $group
                        ));
                        $newGroup->store($connection);
                        $this->groups[$group] = $newGroup;
                        break;
                    case 'fail':
                        throw new NotFoundError(
                            'The group "%s" doesn\'t exist.',
                            $group
                        );
                        break;
                    case 'ignore':
                        return $this;
                }
            }
        } else {
            throw new RuntimeException(
                'Invalid group object: %s',
                var_export($group, 1)
            );
        }

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

    public function listOriginalGroupNames()
    {
        return array_keys($this->storedGroups);
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
            array('go' => $table . 'group_' . $type),
            array()
        )->join(
            array('g' => $table . 'group'),
            'go.' . $type . 'group_id = g.id',
            '*'
        )->where('go.' . $type . '_id = ?', $this->object->id)
        ->order('g.object_name');

        $class = $this->getGroupClass();
        $this->groups = $class::loadAll($connection, $query, 'object_name');
        $this->setBeingLoadedFromDb();

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
        $this->setBeingLoadedFromDb();

        return true;
    }

    public function setBeingLoadedFromDb()
    {
        $this->storedGroups = array();
        foreach ($this->groups as $k => $v) {
            $this->storedGroups[$k] = clone($v);
            $this->storedGroups[$k]->id = $v->id;
        }

        $this->modified = false;
    }

    protected function getGroupClass()
    {
        return __NAMESPACE__ . '\\Icinga' .ucfirst($this->object->getShortTableName()) . 'Group';
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $groups = new static($object);

        if (PrefetchCache::shouldBeUsed()) {
            $groups->groups = PrefetchCache::instance()->groups($object);
            $groups->setBeingLoadedFromDb();
        } else {
            $groups->loadFromDb();
        }

        return $groups;
    }

    public function toConfigString()
    {
        $groups = array_keys($this->groups);

        if (empty($groups)) {
            return '';
        }

        return c::renderKeyValue('groups', c::renderArray($groups));
    }

    public function toLegacyConfigString($additionalGroups = array())
    {
        $groups = array_merge(array_keys($this->groups), $additionalGroups);
        $groups = array_unique($groups);

        if (empty($groups)) {
            return '';
        }

        $type = $this->object->getLegacyObjectType();
        return c1::renderKeyValue($type.'groups', c1::renderArray($groups));
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

    public function __destruct()
    {
        unset($this->storedGroups);
        unset($this->groups);
        unset($this->object);
    }
}
