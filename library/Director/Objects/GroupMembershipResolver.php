<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use InvalidArgumentException;
use LogicException;
use Zend_Db_Select as ZfSelect;

/**
 * Class GroupMembershipResolver
 *
 * - Fetches all involved assignments
 * - Fetch all (or one) object
 * - Fetch all (or one) group
 */
abstract class GroupMembershipResolver
{
    /** @var string Object type, 'host', 'service', 'user' or similar */
    protected $type;

    /** @var string */
    protected $resolverForType = '';

    /** @var array */
    protected $existingMappings;

    /** @var array */
    protected $newMappings;

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var IcingaObject[] */
    protected $objects;

    /** @var IcingaObjectGroup[] */
    protected $groups = array();

    /** @var array */
    protected $staticGroups = array();

    /** @var bool */
    protected $deferred = false;

    /** @var bool */
    protected $checked = false;

    /** @var bool */
    protected $useTransactions = false;

    protected $groupMap;

    public function __construct(Db $connection, $resolverForType = '')
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->resolverForType = $resolverForType;
    }

    /**
     * @return $this
     * @throws \Zend_Db_Adapter_Exception
     */
    public function refreshAllMappings()
    {
        return $this->clearGroups()->clearObjects()->refreshDb(true);
    }

    public function checkDb()
    {
        if ($this->checked) {
            return $this;
        }

        if ($this->isDeferred()) {
            // ensure we are not working with cached data
            IcingaTemplateRepository::clear();
        }

        Benchmark::measure('Rechecking all objects');
        // Only perform a recheck if we are not dealing with hostgroups at the beginning
        if ($this->resolverForType !== 'hostgroup') {
            $this->recheckAllObjects($this->getAppliedGroups());
        }
        if (empty($this->objects) && empty($this->groups)) {
            Benchmark::measure('Nothing to check, got no qualified object');
            return $this;
        }

        Benchmark::measure('Recheck done, loading existing mappings');
        $this->fetchStoredMappings();
        Benchmark::measure('Got stored group mappings');

        $this->checked = true;
        return $this;
    }

    /**
     * @param bool $force
     * @return $this
     * @throws \Zend_Db_Adapter_Exception
     */
    public function refreshDb($force = false)
    {
        if ($force || ! $this->isDeferred()) {
            $this->checkDb();

            if (empty($this->objects) && empty($this->groups)) {
                Benchmark::measure('Nothing to check, got no qualified object');

                return $this;
            }

            Benchmark::measure('Ready, going to store new mappings');
            $this->storeNewMappings();
            $this->removeOutdatedMappings();
            Benchmark::measure('Updated group mappings in db');
        }

        return $this;
    }

    /**
     * @param bool $defer
     * @return $this
     */
    public function defer($defer = true)
    {
        $this->deferred = $defer;
        return $this;
    }

    /**
     * @param $use
     * @return $this
     */
    public function setUseTransactions($use)
    {
        $this->useTransactions = $use;
        return $this;
    }

    public function getType()
    {
        if ($this->type === null) {
            throw new LogicException(sprintf(
                '"type" is required when extending %s, got none in %s',
                __CLASS__,
                get_class($this)
            ));
        }

        return $this->type;
    }

    /**
     * @return bool
     */
    public function isDeferred()
    {
        return $this->deferred;
    }

    /**
     * @param IcingaObject $object
     * @return $this
     */
    public function addObject(IcingaObject $object)
    {
        // Hint: cannot use hasBeenLoadedFromDB, as it is false in onStore()
        //       for new objects
        if (null === ($id = $object->get('id'))) {
            return $this;
        }
        // Disabling for now, how should this work?
        // $this->assertBeenLoadedFromDb($object);
        if ($this->objects === null) {
            $this->objects = [];
        }

        if ($object->isTemplate()) {
            $this->includeChildObjects($object);
        } else {
            $this->objects[$id] = $object;
        }

        return $this;
    }

    /**
     * @param IcingaObject[] $objects
     * @return $this
     */
    public function addObjects(array $objects)
    {
        foreach ($objects as $object) {
            $this->addObject($object);
        }

        return $this;
    }

    protected function includeChildObjects(IcingaObject $object)
    {
        $query = $this->db->select()
            ->from(['o' => $object->getTableName()])
            ->where('o.object_type = ?', 'object');

        IcingaObjectFilterHelper::filterByTemplate(
            $query,
            $object,
            'o',
            Db\IcingaObjectFilterHelper::INHERIT_DIRECT_OR_INDIRECT
        );

        foreach ($object::loadAll($this->connection, $query) as $child) {
            $this->objects[$child->getProperty('id')] = $child;
        }

        return $this;
    }

    /**
     * @param IcingaObject $object
     * @return $this
     */
    public function setObject(IcingaObject $object)
    {
        $this->clearObjects();
        return $this->addObject($object);
    }

    /**
     * @param IcingaObject[] $objects
     * @return $this
     */
    public function setObjects(array $objects)
    {
        $this->clearObjects();
        return $this->addObjects($objects);
    }

    /**
     * @return $this
     */
    public function clearObjects()
    {
        $this->objects = array();
        return $this;
    }

    /**
     * @param IcingaObjectGroup $group
     * @return $this
     */
    public function addGroup(IcingaObjectGroup $group)
    {
        $this->assertBeenLoadedFromDb($group);
        $this->groups[$group->get('id')] = $group;

        $this->checked = false;

        return $this;
    }

    /**
     * @param IcingaObjectGroup[] $groups
     * @return $this
     */
    public function addGroups(array $groups)
    {
        foreach ($groups as $group) {
            $this->addGroup($group);
        }

        $this->checked = false;

        return $this;
    }

    /**
     * @param IcingaObjectGroup $group
     * @return $this
     */
    public function setGroup(IcingaObjectGroup $group)
    {
        $this->clearGroups();
        return $this->addGroup($group);
    }

    /**
     * @param array $groups
     * @return $this
     */
    public function setGroups(array $groups)
    {
        $this->clearGroups();
        return $this->addGroups($groups);
    }

    /**
     * @return $this
     */
    public function clearGroups()
    {
        $this->objects = array();
        $this->checked = false;
        return $this;
    }

    public function getNewMappings()
    {
        if ($this->newMappings !== null && $this->existingMappings !== null) {
            return $this->getDifference($this->newMappings, $this->existingMappings);
        } else {
            return [];
        }
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function storeNewMappings()
    {
        $diff = $this->getNewMappings();
        $count = count($diff);
        if ($count === 0) {
            return;
        }

        $db = $this->db;
        $this->beginTransaction();
        foreach ($diff as $row) {
            $db->insert(
                $this->getResolvedTableName(),
                $row
            );
        }

        $this->commit();
        Benchmark::measure(
            sprintf(
                'Stored %d new resolved group memberships',
                $count
            )
        );
    }

    protected function getGroupId($name)
    {
        $type = $this->type;
        if ($this->groupMap === null) {
            $this->groupMap = $this->db->fetchPairs(
                $this->db->select()->from("icinga_${type}group", ['object_name', 'id'])
            );
        }

        if (array_key_exists($name, $this->groupMap)) {
            return $this->groupMap[$name];
        } else {
            throw new InvalidArgumentException(sprintf(
                'Unable to lookup the group name for "%s"',
                $name
            ));
        }
    }

    public function getOutdatedMappings()
    {
        if ($this->newMappings !== null && $this->existingMappings !== null) {
            return $this->getDifference($this->existingMappings, $this->newMappings);
        } else {
            return [];
        }
    }

    protected function removeOutdatedMappings()
    {
        $diff = $this->getOutdatedMappings();
        $count = count($diff);
        if ($count === 0) {
            return;
        }

        $type = $this->getType();
        $db = $this->db;
        $this->beginTransaction();
        foreach ($diff as $row) {
            $db->delete(
                $this->getResolvedTableName(),
                sprintf(
                    "(${type}group_id = %d AND ${type}_id = %d)",
                    $row["${type}group_id"],
                    $row["${type}_id"]
                )
            );
        }

        $this->commit();
        Benchmark::measure(
            sprintf(
                'Removed %d outdated group memberships',
                $count
            )
        );
    }

    protected function getDifference(&$left, &$right)
    {
        $diff = array();

        $type = $this->getType();
        foreach ($left as $groupId => $objectIds) {
            if (array_key_exists($groupId, $right)) {
                foreach ($objectIds as $objectId) {
                    if (! array_key_exists($objectId, $right[$groupId])) {
                        $diff[] = array(
                            "${type}group_id" => $groupId,
                            "${type}_id"      => $objectId,
                        );
                    }
                }
            } else {
                foreach ($objectIds as $objectId) {
                    $diff[] = array(
                        "${type}group_id" => $groupId,
                        "${type}_id"      => $objectId,
                    );
                }
            }
        }

        return $diff;
    }

    /**
     * This fetches already resolved memberships
     */
    protected function fetchStoredMappings()
    {
        $mappings = array();

        $type = $this->getType();
        $query = $this->db->select()->from(
            array('hgh' => $this->getResolvedTableName()),
            array(
                'group_id'  => "${type}group_id",
                'object_id' => "${type}_id",
            )
        );

        $this->addMembershipWhere($query, "${type}_id", $this->objects);
        $this->addMembershipWhere($query, "${type}group_id", $this->groups);
        if (! empty($this->groups)) {
            // load staticGroups (we touched here) additionally, so we can compare changes
            $this->addMembershipWhere($query, "${type}group_id", $this->staticGroups);
        }

        foreach ($this->db->fetchAll($query) as $row) {
            $groupId = $row->group_id;
            $objectId = $row->object_id;
            if (! array_key_exists($groupId, $mappings)) {
                $mappings[$groupId] = array();
            }

            $mappings[$groupId][$objectId] = $objectId;
        }

        $this->existingMappings = $mappings;
    }

    /**
     * @param ZfSelect $query
     * @param string $column
     * @param IcingaObject[]|int[] $objects
     * @return ZfSelect
     */
    protected function addMembershipWhere(ZfSelect $query, $column, &$objects)
    {
        if (empty($objects)) {
            return $query;
        }

        $ids = array();
        foreach ($objects as $k => $object) {
            if (is_int($object)) {
                $ids[] = $k;
            } elseif (is_string($object)) {
                $ids[] = (int) $object;
            } else {
                $ids[] = (int) $object->get('id');
            }
        }

        if (count($ids) === 1) {
            $query->orWhere($column . ' = ?', $ids[0]);
        } else {
            $query->orWhere($column . ' IN (?)', $ids);
        }

        return $query;
    }

    protected function recheckAllObjects($groups)
    {
        $mappings = [];
        $staticGroups = [];

        if ($this->objects === null) {
            $objects = $this->fetchAllObjects();
        } else {
            $objects = & $this->objects;
        }

        $times = array();

        foreach ($objects as $object) {
            if ($object->shouldBeRemoved()) {
                continue;
            }
            if ($object->isTemplate()) {
                continue;
            }

            $mt = microtime(true);
            $id = $object->get('id');

            DynamicApplyMatches::setType($this->type);
            $resolver = DynamicApplyMatches::prepare($object);
            foreach ($groups as $groupId => $filter) {
                if ($resolver->matchesFilter($filter)) {
                    if (! array_key_exists($groupId, $mappings)) {
                        $mappings[$groupId] = [];
                    }
                    $mappings[$groupId][$id] = $id;
                }
            }

            // can only be run reliably when updating for all groups
            $groupNames = $object->get('groups');
            if (empty($groupNames)) {
                $groupNames = $object->listInheritedGroupNames();
            }
            foreach ($groupNames as $name) {
                $groupId = $this->getGroupId($name);
                if (! array_key_exists($groupId, $mappings)) {
                    $mappings[$groupId] = [];
                }

                $mappings[$groupId][$id] = $id;
                $staticGroups[$groupId] = $groupId;
            }

            $times[] = (microtime(true) - $mt) * 1000;
        }

        $count = count($times);
        $min = $max = $avg = 0;
        if ($count > 0) {
            $min = min($times);
            $max = max($times);
            $avg = array_sum($times) / $count;
        }

        Benchmark::measure(sprintf(
            '%sgroup apply recalculated: objects=%d groups=%d min=%d max=%d avg=%d (in ms)',
            $this->type,
            $count,
            count($groups),
            $min,
            $max,
            $avg
        ));

        Benchmark::measure('Done with single assignments');

        $this->newMappings = $mappings;
        $this->staticGroups = $staticGroups;
    }

    protected function getAppliedGroups()
    {
        if (empty($this->groups)) {
            return $this->fetchAppliedGroups();
        } else {
            return $this->buildAppliedGroups();
        }
    }

    protected function buildAppliedGroups()
    {
        $list = array();
        foreach ($this->groups as $id => $group) {
            $list[$id] = $group->get('assign_filter');
        }

        return $this->parseFilters($list);
    }

    protected function fetchAppliedGroups()
    {
        $type = $this->getType();
        $query = $this->db->select()->from(
            array('hg' => "icinga_${type}group"),
            array(
                'id',
                'assign_filter',
            )
        )->where("assign_filter IS NOT NULL AND assign_filter != ''");

        return $this->parseFilters($this->db->fetchPairs($query));
    }

    /**
     * Parsing a list of query strings to Filter
     *
     * @param string[] $list List of query strings
     *
     * @return Filter[]
     */
    protected function parseFilters($list)
    {
        return array_map(function ($s) {
            return Filter::fromQueryString($s);
        }, $list);
    }

    protected function getTableName()
    {
        $type = $this->getType();
        return "icinga_${type}group_${type}";
    }

    protected function getResolvedTableName()
    {
        return $this->getTableName() . '_resolved';
    }

    /**
     * @return $this
     */
    protected function beginTransaction()
    {
        if ($this->useTransactions) {
            $this->db->beginTransaction();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function commit()
    {
        if ($this->useTransactions) {
            $this->db->commit();
        }

        return $this;
    }

    /**
     * @return IcingaObject[]
     */
    protected function getObjects()
    {
        if ($this->objects === null) {
            $this->objects = $this->fetchAllObjects();
        }

        return $this->objects;
    }

    protected function fetchAllObjects()
    {
        return IcingaObject::loadAllByType($this->getType(), $this->connection);
    }

    protected function assertBeenLoadedFromDb(IcingaObject $object)
    {
        if (! is_int($object->get('id')) && ! ctype_digit($object->get('id'))) {
            throw new LogicException(
                'Group resolver does not support unstored objects'
            );
        }
    }
}
