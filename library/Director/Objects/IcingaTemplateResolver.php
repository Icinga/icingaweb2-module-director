<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;

class IcingaTemplateResolver
{
    protected $object;

    protected $connection;

    protected $db;

    protected $type;

    protected static $templates = array();

    protected static $idIdx = array();

    protected static $nameIdx = array();

    public function __construct(IcingaObject $object)
    {
        $this->setObject($object);
    }

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
        $type = $object->getShortTableName();
        unset(self::$templates[$type]);
    }

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
        // TODO: involve lookup cache
        $res = array();
        $class = $this->object;
        $connection = $this->connection;

        foreach ($this->listResolvedParentIds() as $id) {
            $res[] = $class::loadWithAutoIncId($id, $connection);
        }

        return $res;
    }

    public function listResolvedParentIds()
    {
        $this->requireTemplates();
        return $this->resolveParentIds($this->object->id);
    }

    public function listResolvedParentNames()
    {
        $this->requireTemplates();
        return $this->resolveParentNames($this->object->object_name);
    }

    public function resolveParentIds($id)
    {
        $res = array();

        foreach ($this->listParentIds($id) as $parentId) {
            foreach ($this->resolveParentIds($parentId) as $gpId) {
                $res[] = $gpId;
            }
            $res[] = $parentId;
        }

        return $res;
    }

    public function resolveParentNames($name)
    {
        $res = array();
        foreach ($this->listParentNames($name) as $parentName) {
            foreach ($this->resolveParentNames($parentName) as $gpName) {
                $res[] = $gpName;
            }
            $res[] = $parentName;
        }

        return $res;
    }

    /*
    public function listChildren()
    {
    }

    public function listChildrenIds
    */

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

        $templates = static::fetchTemplates(
            $this->db,
            $type
        );

        $ids = array();
        $names = array();

        foreach ($templates as $row) {
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
        )//->where("o.object_type = 'template'")
         ->order('o.id')
         ->order('i.weight');

        return $db->fetchAll($query);
    }

    public function __destruct()
    {
        unset($this->connection);
        unset($this->db);
        unset($this->object);
    }
}
