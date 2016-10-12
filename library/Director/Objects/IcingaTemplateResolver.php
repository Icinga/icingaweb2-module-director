<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\NestingError;

class IcingaTemplateResolver
{
    protected $object;

    protected $connection;

    protected $db;

    protected $type;

    protected static $templates = array();

    protected static $idIdx = array();

    protected static $nameIdx = array();

    protected static $idToName = array();

    public function __construct(IcingaObject $object)
    {
        $this->setObject($object);
    }

    /**
     * Set a specific object for this resolver instance
     */
    public function setObject(IcingaObject $object)
    {
        $this->object     = $object;
        $this->type       = $object->getShortTableName();
        $this->table      = $object->getTableName();
        $this->connection = $object->getConnection();
        $this->db         = $this->connection->getDbAdapter();

        return $this;
    }

    /**
     * Forget all template relation of the given object type
     *
     * @return self
     */
    public function clearCache()
    {
        unset(self::$templates[$this->type]);
        return $this;
    }

    /**
     * Fetch direct parents
     *
     * return IcingaObject[]
     */
    public function fetchParents()
    {
        // TODO: involve lookup cache
        $res = array();
        $class = $this->object;
        foreach ($this->listParentIds() as $id) {
            $object = $class::loadWithAutoIncId($id, $this->connection);
            $res[$object->object_name] = $object;
        }

        return $res;
    }

    public function listParentIds($id = null)
    {
        $this->requireTemplates();

        if ($id === null) {
            $id = $this->object->id;
        }

        $type = $this->type;

        if (array_key_exists($id, self::$idIdx[$type])) {
            return array_keys(self::$idIdx[$type][$id]);
        }

        return array();
    }

    public function listParentNames($name = null)
    {
        $this->requireTemplates();

        if ($name === null) {
            $name = $this->object->object_name;
        }

        $type = $this->type;

        if (array_key_exists($name, self::$nameIdx[$type])) {
            return array_keys(self::$nameIdx[$type][$name]);
        }

        return array();
    }

    public function fetchResolvedParents()
    {
        return $this->fetchObjectsById($this->listResolvedParentIds());
    }

    public function listResolvedParentIds()
    {
        $this->requireTemplates();
        return $this->resolveParentIds($this->object->id);
    }

    public function listResolvedParentNames()
    {
        $this->requireTemplates();
        if (array_key_exists($name, self::$nameIdx[$type])) {
            return array_keys(self::$nameIdx[$type][$name]);
        }

        return $this->resolveParentNames($this->object->object_name);
    }

    public function listParentsById($id)
    {
        return $this->getNamesForIds($this->resolveParentIds($id));
    }

    public function listParentsByName($name)
    {
        return $this->resolveParentNames($name);
    }

    protected function resolveParentNames($name, &$list = array())
    {
        foreach ($this->listParentNames($name) as $parent) {
            $this->assertNotInList($parent, $list, $id);
            $list[$parent] = true;
            $this->resolveParentIds($parent, $list);
            unset($list[$parent]);
            $list[$parent] = true;
        }

        return array_keys($list);
    }

    protected function resolveParentIds($id, &$list = array())
    {
        foreach ($this->listParentIds($id) as $parent) {
            $this->assertNotInList($parent, $list, $id);
            $list[$parent] = true;
            $this->resolveParentIds($parent, $list);
            unset($list[$parent]);
            $list[$parent] = true;
        }

        return array_keys($list);
    }

    protected function assertNotInList($id, & $list, $root)
    {
        if (array_key_exists($id, $list)) {
            $list = array_keys($list);
            array_unshift($list, $root);
            throw new NestingError(
                'Loop detected: %s',
                implode(' -> ', $this->getNamesForIds($list))
            );
        }
    }

    protected function getNamesForIds($ids)
    {
        $names = array();
        foreach ($ids as $id) {
            $names[] = $this->getNameForId($id);
        }

        return $names;
    }

    protected function getNameForId($id)
    {
        return self::$idToName[$this->type][$id];
    }

    protected function fetchObjectsById($ids)
    {
        $class = $this->object;
        $connection = $this->connection;
        $res = array();

        foreach ($ids as $id) {
            $res[] = $class::loadWithAutoIncId($id, $connection);
        }

        return $res;
    }

    protected function requireTemplates()
    {
        if (! array_key_exists($this->type, self::$templates)) {
            $this->prepareLookupTables();
        }

        return $this;
    }

    protected function prepareLookupTables()
    {
        $type = $this->type;

        $templates = $this->fetchTemplates();

        $ids = array();
        $names = array();
        $idToName = array();

        foreach ($templates as $row) {
            $idToName[$row->id] = $row->name;

            if ($row->parent_id === null) {
                continue;
            }
            if (array_key_exists($row->id, $ids)) {
                $ids[$row->id][$row->parent_id] = $row->parent_name;
                $names[$row->name][$row->parent_name] = $row->parent_id;
            } else {
                $ids[$row->id] = array(
                    $row->parent_id => $row->parent_name
                );

                $names[$row->name] = array(
                    $row->parent_name => $row->parent_id
                );
            }
        }

        self::$idIdx[$type]     = $ids;
        self::$nameIdx[$type]   = $names;
        self::$templates[$type] = $templates;
        self::$idToName[$type]  = $idToName;
    }

    protected function fetchTemplates()
    {
        $db    = $this->db;
        $type  = $this->type;
        $table = $this->object->getTableName();

        $query = $db->select()->from(
            array('o' => $table),
            array(
                'id'          => 'o.id',
                'name'        => 'o.object_name',
                'parent_id'   => 'p.id',
                'parent_name' => 'p.object_name',
            )
        )->joinLeft(
            array('i' => $table . '_inheritance'),
            'o.id = i.' . $type . '_id',
            array()
        )->joinLeft(
            array('p' => $table),
            'p.id = i.parent_' . $type . '_id',
            array()
        )->order('o.id')->order('i.weight');

        return $db->fetchAll($query);
    }

    public function __destruct()
    {
        unset($this->connection);
        unset($this->db);
        unset($this->object);
    }
}
