<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;
use RuntimeException;

class TemplateTree
{
    protected $connection;

    protected $db;

    protected $parents;

    protected $children;

    protected $rootNodes;

    protected $tree;

    protected $type;

    protected $objectMaps;

    protected $names;

    protected $templateNameToId;

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

    public function listParentNamesFor(IcingaObject $object)
    {
        $id = (int) $object->getProperty('id');
        $this->requireTree();

        if (array_key_exists($id, $this->parents)) {
            return array_values($this->parents[$id]);
        }

        $this->requireObjectMaps();

        $parents = [];
        if (array_key_exists($id, $this->objectMaps)) {
            foreach ($this->objectMaps[$id] as $pid) {
                if (array_key_exists($pid, $this->names)) {
                    $parents[] = $this->names[$pid];
                } else {
                    throw new RuntimeException(sprintf(
                        'Got invalid parent id %d for %s "%s"',
                        $pid,
                        $this->type,
                        $object->getObjectName()
                    ));
                }
            }
        }

        return $parents;
    }

    protected function loadObjectMaps()
    {
        $this->requireTree();

        $map = [];
        $db    = $this->db;
        $type  = $this->type;
        $table = "icinga_{$type}_inheritance";

        $query = $db->select()->from(
            ['i' => $table],
            [
                'object' => "i.{$type}_id",
                'parent' => "i.parent_{$type}_id",
            ]
        )->order('i.weight');

        foreach ($db->fetchAll($query) as $row) {
            $id = (int) $row->object;
            if (! array_key_exists($id, $map)) {
                $map[$id] = [];
            }
            $map[$id][] = (int) $row->parent;
        }

        $this->objectMaps = $map;
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
        // can not use hasBeenLoadedFromDb() when in onStore()
        $id = $object->getProperty('id');
        if ($id !== null) {
            return $this->getParentsById($object->getProperty('id'));
        } else {
            throw new RuntimeException(
                'Loading parents for unstored objects has not been implemented yet'
            );
            // return $this->getParentsForUnstoredObject($object);
        }
    }

    public function getAncestorsFor(IcingaObject $object)
    {
        if (
            $object->hasBeenModified()
            && $object->gotImports()
            && $object->imports()->hasBeenModified()
        ) {
            return $this->getAncestorsForUnstoredObject($object);
        } else {
            return $this->getAncestorsById($object->getProperty('id'));
        }
    }

    protected function getAncestorsForUnstoredObject(IcingaObject $object)
    {
        $this->requireTree();
        $ancestors = [];
        foreach ($object->imports() as $import) {
            $name = $import->getObjectName();
            if ($import->hasBeenLoadedFromDb()) {
                $pid = (int) $import->get('id');
            } else {
                if (! array_key_exists($name, $this->templateNameToId)) {
                    continue;
                }
                $pid = $this->templateNameToId[$name];
            }

            $this->getAncestorsById($pid, $ancestors);

            // Hint: inheritance order matters
            if (false !== ($key = array_search($name, $ancestors))) {
                // Note: this used to be just unset($ancestors[$key]), and that
                // broke Apply Rules inheriting from Templates with the same name
                // in a way that related fields no longer showed up (#1602)
                // This new if relaxes this and doesn't unset in case the name
                // matches the original object name. However, I'm still unsure why
                // this was required at all.
                if ($name !== $object->getObjectName()) {
                    unset($ancestors[$key]);
                }
            }
            $ancestors[$pid] = $name;
        }

        return $ancestors;
    }

    protected function requireObjectMaps()
    {
        if ($this->objectMaps === null) {
            $this->loadObjectMaps();
        }
    }

    public function getParentsById($id)
    {
        $this->requireTree();

        if (array_key_exists($id, $this->parents)) {
            return $this->parents[$id];
        }

        $this->requireObjectMaps();
        if (array_key_exists($id, $this->objectMaps)) {
            $parents = [];
            foreach ($this->objectMaps[$id] as $pid) {
                $parents[$pid] = $this->names[$pid];
            }

            return $parents;
        } else {
            return [];
        }
    }

    /**
     * @param $id
     * @param $list
     * @throws NestingError
     */
    protected function assertNotInList($id, &$list)
    {
        if (array_key_exists($id, $list)) {
            $list = array_keys($list);
            array_push($list, $id);

            if (is_int($id)) {
                throw new NestingError(
                    'Loop detected: %s',
                    implode(' -> ', $this->getNamesForIds($list, true))
                );
            } else {
                throw new NestingError(
                    'Loop detected: %s',
                    implode(' -> ', $list)
                );
            }
        }
    }

    protected function getNamesForIds($ids, $ignoreErrors = false)
    {
        $names = [];
        foreach ($ids as $id) {
            $names[] = $this->getNameForId($id, $ignoreErrors);
        }

        return $names;
    }

    protected function getNameForId($id, $ignoreErrors = false)
    {
        if (! array_key_exists($id, $this->names)) {
            if ($ignoreErrors) {
                return "id=$id";
            } else {
                throw new InvalidArgumentException("Got no name for $id");
            }
        }

        return $this->names[$id];
    }

    /**
     * @param $id
     * @param array $ancestors
     * @param array $path
     * @return array
     * @throws NestingError
     */
    public function getAncestorsById($id, &$ancestors = [], $path = [])
    {
        $path[$id] = true;
        foreach ($this->getParentsById($id) as $pid => $name) {
            $this->assertNotInList($pid, $path);
            $path[$pid] = true;

            $this->getAncestorsById($pid, $ancestors, $path);
            unset($path[$pid]);

            // Hint: inheritance order matters
            if (false !== ($key = array_search($name, $ancestors))) {
                unset($ancestors[$key]);
            }
            $ancestors[$pid] = $name;
        }
        unset($path[$id]);

        return $ancestors;
    }

    public function getChildrenFor(IcingaObject $object)
    {
        // can not use hasBeenLoadedFromDb() when in onStore()
        $id = $object->getProperty('id');
        if ($id !== null) {
            return $this->getChildrenById($id);
        } else {
            throw new RuntimeException(
                'Loading children for unstored objects has not been implemented yet'
            );
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
        // can not use hasBeenLoadedFromDb() when in onStore()
        $id = $object->getProperty('id');
        if ($id !== null) {
            return $this->getDescendantsById($id);
        } else {
            throw new RuntimeException(
                'Loading descendants for unstored objects has not been implemented yet'
            );
            // return $this->getDescendantsForUnstoredObject($object);
        }
    }

    public function getDescendantsById($id, &$children = [], &$path = [])
    {
        $path[$id] = true;
        foreach ($this->getChildrenById($id) as $pid => $name) {
            $this->assertNotInList($pid, $path);
            $path[$pid] = true;
            $this->getDescendantsById($pid, $children, $path);
            unset($path[$pid]);
            $children[$pid] = $name;
        }
        unset($path[$id]);

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
            throw new RuntimeException(
                'Partial tree fetching has not been implemented yet'
            );
            // return $this->partialTree($parentId);
        }
    }

    protected function returnFullTree()
    {
        $result = $this->rootNodes;
        foreach ($result as $id => &$node) {
            $this->addChildrenById($id, $node);
        }

        return $result;
    }

    protected function addChildrenById($pid, array &$base)
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
        Benchmark::measure(sprintf('Prepare "%s" Template Tree', $this->type));
        $templates = $this->fetchTemplates();
        $parents = [];
        $rootNodes = [];
        $children = [];
        $names = [];
        foreach ($templates as $row) {
            $id = (int) $row->id;
            $pid = (int) $row->parent_id;
            $names[$id] = $row->name;
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

            $names[$pid] = $row->parent_name;
            $parents[$id][$pid] = $row->parent_name;

            if (! array_key_exists($pid, $children)) {
                $children[$pid] = [];
            }

            $children[$pid][$id] = $row->name;
        }

        $this->parents   = $parents;
        $this->children  = $children;
        $this->rootNodes = $rootNodes;
        $this->names = $names;
        $this->templateNameToId = array_flip($names);
        Benchmark::measure(sprintf('"%s" Template Tree ready', $this->type));
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

        if ($type === 'command') {
            $joinCondition = $db->quoteInto(
                "p.id = i.parent_{$type}_id",
                'template'
            );
        } else {
            $joinCondition = $db->quoteInto(
                "p.id = i.parent_{$type}_id AND p.object_type = ?",
                'template'
            );
        }

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
            $joinCondition,
            []
        )->order('o.object_name')->order('i.weight');

        if ($type !== 'command') {
            $query->where(
                'o.object_type = ?',
                'template'
            );
        }

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
