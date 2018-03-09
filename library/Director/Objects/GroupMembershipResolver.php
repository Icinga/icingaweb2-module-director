<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
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

    /** @var bool */
    protected $deferred = false;

    /** @var bool */
    protected $useTransactions = false;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    /**
     * @return $this
     */
    public function refreshAllMappings()
    {
        return $this->clearGroups()->clearObjects()->refreshDb(true);
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function refreshDb($force = false)
    {
        if ($force || ! $this->isDeferred()) {
            Benchmark::measure('Going to refresh all group mappings');
            $this->fetchStoredMappings();
            Benchmark::measure('Got stored HG mappings, rechecking all objects');
            $this->recheckAllObjects($this->getAppliedGroups());
            Benchmark::measure('Ready, going to store new mappings');
            $this->storeNewMappings();
            $this->removeOutdatedMappings();
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
            throw new ProgrammingError(
                '"type" is required when extending %s, got none in %s',
                __CLASS__,
                get_class($this)
            );
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
        if (! $id = $object->get('id')) {
            return $this;
        }
        // Disabling for now, how should this work?
        // $this->assertBeenLoadedFromDb($object);
        if ($this->objects === null) {
            $this->objects = array();
        }

        $this->objects[$object->get('id')] = $object;
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

        if ($group->get('assign_filter') !== null) {
            $this->groups[$group->get('id')] = $group;
        }

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
        return $this;
    }

    protected function storeNewMappings()
    {
        $diff = $this->getDifference($this->newMappings, $this->existingMappings);
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

    protected function removeOutdatedMappings()
    {
        $diff = $this->getDifference($this->existingMappings, $this->newMappings);
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

    protected function getDifference(& $left, & $right)
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
     * @param IcingaObject[] $objects
     * @return ZfSelect
     */
    protected function addMembershipWhere(ZfSelect $query, $column, & $objects)
    {
        if (empty($objects)) {
            return $query;
        }

        $ids = array();
        foreach ($objects as $object) {
            $ids[] = (int) $object->get('id');
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
        $mappings = array();

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

            // TODO: fix this last hard host dependency
            $resolver = HostApplyMatches::prepare($object);
            foreach ($groups as $groupId => $filter) {
                if ($resolver->matchesFilter(Filter::fromQueryString($filter))) {
                    if (! array_key_exists($groupId, $mappings)) {
                        $mappings[$groupId] = array();
                    }

                    $id = $object->get('id');
                    $mappings[$groupId][$id] = $id;
                }
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
            'Hostgroup apply recalculated: objects=%d groups=%d min=%d max=%d avg=%d (in ms)',
            $count,
            count($groups),
            $min,
            $max,
            $avg
        ));

        foreach ($this->fetchMissingSingleAssignments() as $row) {
            $mappings[$row->group_id][$row->object_id] = $row->object_id;
        }

        Benchmark::measure('Done with single assignments');

        $this->newMappings = $mappings;
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

        return $list;
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
        )->where('assign_filter IS NOT NULL');

        return $this->db->fetchPairs($query);
    }

    protected function fetchMissingSingleAssignments()
    {
        $type = $this->getType();
        $query = $this->db->select()->from(
            array("go" => $this->getTableName()),
            array(
                'object_id' => "${type}_id",
                'group_id'  => "${type}group_id",
            )
        )->joinLeft(
            array("gor" => $this->getResolvedTableName()),
            "go.${type}_id = gor.${type}_id AND go.${type}group_id = gor.${type}group_id",
            array()
        );

        $this->addMembershipWhere($query, "go.${type}_id", $this->objects);
        $this->addMembershipWhere($query, "go.${type}group_id", $this->groups);

        // Order matters, this must be AND:
        $query->where("gor.${type}_id IS NULL");

        return $this->db->fetchAll($query);
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
        if (! ctype_digit($object->get('id'))) {
            throw new ProgrammingError(
                'Group resolver does not support unstored objects'
            );
        }
    }
}
