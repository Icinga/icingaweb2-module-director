<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Exception\NotImplementedError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class TemplateTree
{
    protected $connection;

    protected $db;

    protected $parents;

    protected $children;

    protected $rootNodes;

    protected $tree;

    public function __construct($type, Db $connection)
    {
        $this->type = $type;
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getType()
    {
        return $this->type;
    }

    public function listParentIdsFor(IcingaObject $object)
    {
        return array_keys($this->getParentsFor($object));
    }

    public function listAncestorIdsFor(IcingaObject $object)
    {
        return array_keys($this->getAncestorsFor($object));
    }

    public function listChildIdsFor(IcingaObject $object)
    {
        return array_keys($this->getChildrenFor($object));
    }

    public function listDescendantIdsFor(IcingaObject $object)
    {
        return array_keys($this->getDescendantsFor($object));
    }

    public function getParentsFor(IcingaObject $object)
    {
        if ($object->hasBeenLoadedFromDb()) {
            return $this->getParentsById($object->getProperty('id'));
        } else {
            throw new NotImplementedError('Not yet');
            // return $this->getParentsForUnstoredObject($object);
        }
    }

    public function getAncestorsFor(IcingaObject $object)
    {
        if ($object->hasBeenLoadedFromDb()) {
            return $this->getAncestorsById($object->getProperty('id'));
        } else {
            throw new NotImplementedError('Not yet');
            // return $this->getAncestorsForUnstoredObject($object);
        }
    }

    public function getParentsById($id)
    {
        $this->requireTree();

        if (array_key_exists($id, $this->parents)) {
            return $this->parents[$id];
        } else {
            return [];
        }
    }

    public function getAncestorsById($id, & $ancestors = [])
    {
        foreach ($this->getParentsById($id) as $pid => $name) {
            $this->getAncestorsById($pid, $ancestors);

            // Hint: inheritance order matters
            if (false !== ($key = array_search($name, $ancestors))) {
                unset($ancestors[$key]);
            }

            $ancestors[$pid] = $name;
        }

        return $ancestors;
    }

    public function getChildrenFor(IcingaObject $object)
    {
        if ($object->hasBeenLoadedFromDb()) {
            return $this->getChildrenById($object->getProperty('id'));
        } else {
            throw new NotImplementedError('Not yet');
            // return $this->getChildrenForUnstoredObject($object);
        }
    }

    public function getChildrenById($id)
    {
        $this->requireTree();

        if (array_key_exists($id, $this->children)) {
            return $this->children[$id];
        } else {
            return [];
        }
    }

    public function getDescendantsFor(IcingaObject $object)
    {
        if ($object->hasBeenLoadedFromDb()) {
            return $this->getDescendantsById($object->getProperty('id'));
        } else {
            throw new NotImplementedError('Not yet');
            // return $this->getDescendantsForUnstoredObject($object);
        }
    }

    public function getDescendantsById($id, & $children = [])
    {
        foreach ($this->getChildrenById($id) as $pid => $name) {
            $this->getDescendantsById($pid, $children);
            $children[$pid] = $name;
        }

        return $children;
    }

    public function getTree($parentId = null)
    {
        if ($this->tree === null) {
            $this->prepareTree();
        }

        if ($parentId === null) {
            return $this->returnFullTree();
        } else {
            return $this->partialTree($parentId);
        }
    }

    protected function returnFullTree()
    {
        $result = $this->rootNodes;
        foreach ($result as $id => & $node) {
            $this->addChildrenById($id, $node);
        }

        return $result;
    }

    protected function addChildrenById($pid, array & $base)
    {
        foreach ($this->getChildrenById($pid) as $id => $name) {
            $base['children'][$id] = [
                'name'     => $name,
                'children' => []
            ];
            $this->addChildrenById($id, $base['children'][$id]);
        }
    }

    protected function prepareTree()
    {
        $templates = $this->fetchTemplates();
        $parents = [];
        $rootNodes = [];
        $children = [];
        foreach ($templates as $row) {
            $id = (int) $row->id;
            $pid = (int) $row->parent_id;
            if (! array_key_exists($id, $parents)) {
                $parents[$id] = [];
            }

            if ($row->parent_id === null) {
                $rootNodes[$id] = [
                    'name' => $row->name,
                    'children' => []
                ];
                continue;
            }

            $parents[$id][$pid] = $row->parent_name;

            if (! array_key_exists($pid, $children)) {
                $children[$pid] = [];
            }

            $children[$pid][$id] = $row->name;
        }

        $this->parents   = $parents;
        $this->children  = $children;
        $this->rootNodes = $rootNodes;
    }

    public function fetchObjects()
    {
        //??
    }

    protected function requireTree()
    {
        if ($this->parents === null) {
            $this->prepareTree();
        }
    }

    public function fetchTemplates()
    {
        $db    = $this->db;
        $type  = $this->type;
        $table = "icinga_$type";

        $query = $db->select()->from(
            ['o' => $table],
            [
                'id'          => 'o.id',
                'name'        => 'o.object_name',
                'object_type' => 'o.object_type',
                'parent_id'   => 'p.id',
                'parent_name' => 'p.object_name',
            ]
        )->joinLeft(
            ['i' => $table . '_inheritance'],
            'o.id = i.' . $type . '_id',
            []
        )->joinLeft(
            ['p' => $table],
            $db->quoteInto(
                'p.id = i.parent_' . $type . '_id AND p.object_type = ?',
                'template'
            ),
            []
        )/*->where(
            'p.object_type = ?',
            'template'
        )*/->where(
            'o.object_type = ?',
            'template'
        )->order('o.id')->order('i.weight');
// echo '<pre style="padding-top: 6em">' . $query . '</pre>';

        return $db->fetchAll($query);
    }
}

/**
 *
SELECT o.id, o.object_name AS name, o.object_type, p.id AS parent_id,
 p.object_name AS parent_name FROM icinga_service AS o
RIGHT JOIN icinga_service_inheritance AS i ON o.id = i.service_id
RIGHT JOIN icinga_service AS p ON p.id = i.parent_service_id
 WHERE (p.object_type = 'template') AND (o.object_type = 'template')
 ORDER BY o.id ASC, i.weight ASC

 */
