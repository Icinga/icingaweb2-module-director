<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\DynamicApplyMatches;
use Zend_Db_Adapter_Abstract as ZfDB;

class IcingaObjectResolver
{
    /** @var ZfDB */
    protected $db;

    protected $nameMaps;

    protected $baseTable = 'not_configured';

    protected $ignoredProperties = [];

    protected $relatedTables = [];

    protected $booleans = [];

    /**
     * @var array[]
     */
    protected $templates;

    /**
     * @var array[]
     */
    protected $resolvedTemplateProperties;

    /**
     * @var array
     */
    protected $inheritancePaths;

    protected $templateVars;

    protected $resolvedTemplateVars = [];

    protected $groupMemberShips;

    protected $resolvedGroupMemberShips;

    public function __construct(ZfDb $db)
    {
        // TODO: loop detection. Not critical right now, as this isn't the main resolver
        Benchmark::measure('Object Resolver for ' . $this->baseTable . ' warming up');
        $this->db = $db;
        // Fetch: ignore disabled?
        $this->prepareNameMaps();
        $this->templates = [];
        foreach ($this->fetchPlainObjects($this->baseTable, 'template') as $template) {
            $id = $template->id;
            $this->stripIgnoredProperties($template);
            // Let's to this only on final objects:
            // $this->replaceRelatedNames($object);
            // $this->convertBooleans($object);
            $this->stripNullProperties($template);
            $this->templates[$id] = (array) $template;
        }
        $this->templateVars = $this->fetchTemplateVars();
        // Using already resolved data, so this is unused right now:
        // $this->groupMemberShips = $this->fetchAllGroups();
        $this->resolvedGroupMemberShips = $this->fetchAllResolvedGroups();
        $this->inheritancePaths = $this->fetchInheritancePaths($this->baseTable, 'host_id');
        foreach ($this->inheritancePaths as $path) {
            if (! isset($this->resolvedTemplateProperties[$path])) {
                $properties = (object) $this->getResolvedProperties($path);
                $this->replaceRelatedNames($properties);
                $this->convertBooleans($properties);
                $this->resolvedTemplateProperties[$path] = $properties;
                $this->resolvedTemplateVars[$path] = $this->getResolvedVars($path);
            }
        }

        Benchmark::measure('Object Resolver for ' . $this->baseTable . ' is ready');

        // Notes:
        // default != null:
        // most icinga objects: disabled => n
        // Icinga(ScheduledDowntime|TimePeriod)Range: range_type => include, merge_behaviour => set
        // IcingaTemplateChoice: min_required => 0, max_allowed => 1
        // IcingaZone: is_global => n
        // ImportSource: import_state => unknown
        // SyncRule: sync_state => unknown
    }

    protected static function addUniqueMembers(&$list, $newMembers)
    {
        foreach ($newMembers as $member) {
            $pos = \array_search($member, $list);
            if ($pos !== false) {
                unset($list[$pos]);
            }

            \array_unshift($list, $member);
        }
    }

    public function fetchResolvedObjects()
    {
        $objects = [];
        $allVars = $this->fetchNonTemplateVars();
        foreach ($this->fetchPlainObjects($this->baseTable, 'object') as $object) {
            $id = $object->id; // id will be stripped
            $objects[$id] = $this->enrichObject($object, $allVars);
        }

        return $objects;
    }

    public function fetchObjectsMatchingFilter(Filter $filter)
    {
        DynamicApplyMatches::setType($this->getType());
        DynamicApplyMatches::fixFilterColumns($filter);
        $objects = [];
        $allVars = $this->fetchNonTemplateVars();
        foreach ($this->fetchPlainObjects($this->baseTable, 'object') as $object) {
            $id = $object->id; // id will be stripped
            if ($filter->matches($object)) {
                $objects[$id] = $this->enrichObject($object, $allVars);
            }
        }

        return $objects;
    }

    protected function enrichObject($object, $allVars)
    {
        $id = $object->id;
        $this->stripIgnoredProperties($object);
        if (isset($allVars[$id])) {
            $vars = $allVars[$id];
        } else {
            $vars = [];
        }
        $vars += $this->getInheritedVarsById($id);

        // There is no merge, +/-, not yet. Unused, as we use resolved groups:
        // if (isset($this->groupMemberShips[$id])) {
        //     $groups = $this->groupMemberShips[$id];
        // } else {
        //     $groups = $this->getInheritedGroupsById($id);
        // }
        if (isset($this->resolvedGroupMemberShips[$id])) {
            $groups = $this->resolvedGroupMemberShips[$id];
        } else {
            $groups = [];
        }

        foreach ($this->getInheritedPropertiesById($id) as $property => $value) {
            if (! isset($object->$property)) {
                $object->$property = $value;
            }
        }
        $this->replaceRelatedNames($object);
        $this->convertBooleans($object);
        $this->stripNullProperties($object);
        if (! empty($vars)) {
            $object->vars = (object) $vars;
            static::flattenVars($object);
        }
        if (! empty($groups)) {
            $object->groups = $groups;
        }

        return $object;
    }

    /**
     * @param string $baseTable e.g. icinga_host
     * @param string $relColumn e.g. host_id
     * @return array
     */
    protected function fetchInheritancePaths($baseTable, $relColumn)
    {
        // select host_id, GROUP_CONCAT(parent_host_id ORDER BY weight) FROM icinga_host_inheritance group by host_id;
        $query = $this->db->select()
            ->from([
                'oi' => "${baseTable}_inheritance"
            ], [
                $relColumn,
                "GROUP_CONCAT(parent_$relColumn ORDER BY weight SEPARATOR ',')"
            ])
            ->group($relColumn)
            ->order("LENGTH(GROUP_CONCAT(parent_$relColumn ORDER BY weight SEPARATOR ','))");

        // pgsql: ARRAY_TO_STRING(ARRAY_AGG(---), ',') -> order?

        return $this->db->fetchPairs($query);
    }

    protected function getInheritedPropertiesById($objectId)
    {
        if (isset($this->inheritancePaths[$objectId])) {
            return $this->getResolvedProperties($this->inheritancePaths[$objectId]);
        } else {
            return [];
        }
    }

    protected function getInheritedVarsById($objectId)
    {
        if (isset($this->inheritancePaths[$objectId])) {
            return $this->getResolvedVars($this->inheritancePaths[$objectId]);
        } else {
            return [];
        }
    }

    protected function getInheritedGroupsById($objectId)
    {
        if (isset($this->inheritancePaths[$objectId])) {
            return $this->getResolvedGroups($this->inheritancePaths[$objectId]);
        } else {
            return [];
        }
    }

    /**
     * @param $path
     * @return array[]
     */
    protected function getResolvedProperties($path)
    {
        $pos = \strpos($path, ',');
        if ($pos === false) {
            return $this->templates[$path];
        } else {
            $first = \substr($path, 0, $pos);
            $parentPath = \substr($path, $pos + 1);
            $result = $this->templates[$first]
                + $this->getResolvedProperties($parentPath);
            unset($result['object_name']);

            return $result;
        }
    }

    protected function getResolvedVars($path)
    {
        $pos = \strpos($path, ',');
        if ($pos === false) {
            if (isset($this->templateVars[$path])) {
                return $this->templateVars[$path];
            } else {
                return [];
            }
        } else {
            $first = \substr($path, 0, $pos);
            $parentPath = \substr($path, $pos + 1);
            return $this->getResolvedVars($first)
                + $this->getResolvedVars($parentPath);
        }
    }

    protected function getResolvedGroups($path)
    {
        $pos = \strpos($path, ',');
        if ($pos === false) {
            if (isset($this->groupMemberShips[$path])) {
                return $this->groupMemberShips[$path];
            } else {
                return [];
            }
        } else {
            $first = \substr($path, 0, $pos);
            $parentPath = \substr($path, $pos + 1);
            $currentGroups = $this->getResolvedVars($first);

            // There is no merging +/-, not yet
            if (empty($currentGroups)) {
                return $this->getResolvedVars($parentPath);
            } else {
                return $currentGroups;
            }
        }
    }

    protected function cleanupObjects(&$objects)
    {
        foreach ($objects as $object) {
            $this->stripIgnoredProperties($object);
            $this->replaceRelatedNames($object);
            $this->convertBooleans($object);
        }
    }

    protected function fetchPlainObjects($table, $objectType = null)
    {
        $query = $this->db->select()
            ->from(['o' => $table])
            ->order('o.object_name');

        if ($objectType !== null) {
            $query->where('o.object_type = ?', $objectType);
        }

        return $this->db->fetchAll($query);
    }


    /**
     * @param \stdClass $object
     */
    protected function replaceRelatedNames($object)
    {
        foreach ($this->nameMaps as $property => $map) {
            if (\property_exists($object, $property)) {
                //  Hint: substr strips _id
                if ($object->$property === null) {
                    $object->{\substr($property, 0, -3)} = null;
                } else {
                    $object->{\substr($property, 0, -3)} = $map[$object->$property];
                }
                unset($object->$property);
            }
        }
    }

    protected function translateTemplateIdsToNames($ids)
    {
        $names = [];
        foreach ($ids as $id) {
            if (isset($this->templates[$id])) {
                $names[] = $this->templates[$id]->object_name;
            } else {
                throw new \RuntimeException("There is no template with ID $id");
            }
        }

        return $names;
    }

    protected function stripIgnoredProperties($object)
    {
        foreach ($this->ignoredProperties as $key) {
            unset($object->$key);
        }
    }

    public function prepareNameMaps()
    {
        // TODO: fetch from dummy Object? How to ignore irrelevant ones like choices?
        $relatedNames = [];
        foreach ($this->relatedTables as $key => $relatedTable) {
            $relatedNames[$key] = $this->fetchRelationMap($this->baseTable, $relatedTable, $key);
        }

        $this->nameMaps = $relatedNames;
    }

    protected function convertBooleans($object)
    {
        foreach ($this->booleans as $property) {
            if (\property_exists($object, $property) && $object->$property !== null) {
                //  Hint: substr strips _id
                $object->$property = $object->$property === 'y';
            }
        }
    }

    protected function stripNullProperties($object)
    {
        foreach (\array_keys((array) $object) as $property) {
            if ($object->$property === null) {
                unset($object->$property);
            }
        }
    }

    protected function fetchRelationMap($sourceTable, $destinationTable, $property)
    {
        $query = $this->db->select()
            ->from(['d' => $destinationTable], ['d.id', 'd.object_name'])
            ->join(['o' => $sourceTable], "d.id = o.$property", [])
            ->order('d.object_name');

        return $this->db->fetchPairs($query);
    }

    protected function fetchTemplateVars()
    {
        $query = $this->prepareVarsQuery()->where('o.object_type = ?', 'template');
        return $this->fetchAndCombineVars($query);
    }

    protected function fetchNonTemplateVars()
    {
        $query = $this->prepareVarsQuery()->where('o.object_type != ?', 'template');
        return $this->fetchAndCombineVars($query);
    }

    protected function fetchAndCombineVars($query)
    {
        $vars = [];
        foreach ($this->db->fetchAll($query) as $var) {
            $id = $var->object_id;
            if (! isset($vars[$id])) {
                $vars[$id] = [];
            }
            if ($var->format === 'json') {
                $vars[$id][$var->varname] = \json_decode($var->varvalue);
            } else {
                $vars[$id][$var->varname] = $var->varvalue;
            }
        }

        return $vars;
    }

    protected function fetchAllGroups()
    {
        $query = $this->prepareGroupsQuery();
        return $this->fetchAndCombineGroups($query);
    }

    protected function fetchAllResolvedGroups()
    {
        $query = $this->prepareGroupsQuery(true);
        return $this->fetchAndCombineGroups($query);
    }

    protected function fetchAndCombineGroups($query)
    {
        $groups = [];
        foreach ($this->db->fetchAll($query) as $group) {
            $id = $group->object_id;
            if (isset($groups[$id])) {
                $groups[$id][$group->group_id] = $group->group_name;
            } else {
                $groups[$id] = [
                    $group->group_id => $group->group_name
                ];
            }
        }

        return $groups;
    }

    protected function prepareGroupsQuery($resolved = false)
    {
        $type = $this->getType();
        $groupsTable = $this->baseTable . 'group';
        $groupMembershipTable = "${groupsTable}_$type";
        if ($resolved) {
            $groupMembershipTable .= '_resolved';
        }
        $oRef = "${type}_id";
        $gRef = "${type}group_id";

        return $this->db->select()
            ->from(['gm' => $groupMembershipTable], [
                'object_id'  => $oRef,
                'group_id'   => $gRef,
                'group_name' => 'g.object_name'
            ])
            ->join(['g' => $groupsTable], "g.id = gm.$gRef", [])
            ->order("gm.$oRef")
            ->order('g.object_name');
    }

    protected function prepareVarsQuery()
    {
        $table = $this->baseTable . '_var';
        $ref = $this->getType() . '_id';
        return $this->db->select()
            ->from(['v' => $table], [
                'object_id' => $ref,
                'v.varname',
                'v.varvalue',
                'v.format',
                // 'v.checksum',
            ])
            ->join(['o' => $this->baseTable], "o.id = v.$ref", [])
            ->order('o.id')
            ->order('v.varname');
    }

    protected function getType()
    {
        return \preg_replace('/^icinga_/', '', $this->baseTable);
    }

    /**
     * Helper, flattens all vars of a given object
     *
     * The object itself will be modified, and the 'vars' property will be
     * replaced with corresponding 'vars.whatever' properties
     *
     * @param $object
     * @param string $key
     */
    protected static function flattenVars(\stdClass $object, $key = 'vars')
    {
        if (property_exists($object, $key)) {
            foreach ($object->vars as $k => $v) {
                if (is_object($v)) {
                    static::flattenVars($v, $k);
                }
                $object->{$key . '.' . $k} = $v;
            }
            unset($object->$key);
        }
    }
}
