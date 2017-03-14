<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Zend_Db_Select as ZfSelect;

/**
 * Class HostGroupMembershipResolver
 *
 * - Fetches all involved assignments
 * - Fetch all (or one) host
 * - Fetch all (or one) group
 */
class HostGroupMembershipResolver
{
    /** @var array */
    protected $existingMappings;

    /** @var array */
    protected $newMappings;

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var IcingaHost[] */
    protected $hosts;

    /** @var IcingaHostGroup[] */
    protected $hostgroups = array();

    protected $table = 'icinga_hostgroup_host_resolved';

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
        return $this->clearHostgroups()->clearHosts()->refreshDb(true);
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function refreshDb($force = false)
    {
        if ($force || ! $this->isDeferred()) {
            Benchmark::measure('Going to refresh all hostgroup mappings');
            $this->fetchStoredMappings();
            Benchmark::measure('Got stored HG mappings, rechecking all hosts');
            $this->recheckAllHosts($this->getAppliedHostgroups());
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

    /**
     * @return bool
     */
    public function isDeferred()
    {
        return $this->deferred;
    }

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function addHost(IcingaHost $host)
    {
        $this->assertBeenLoadedFromDb($host);
        if ($this->hosts === null) {
            $this->hosts = array();
        }

        $this->hosts[$host->get('id')] = $host;
        return $this;
    }

    /**
     * @param IcingaHost[] $hosts
     * @return $this
     */
    public function addHosts(array $hosts)
    {
        foreach ($hosts as $host) {
            $this->addHost($host);
        }

        return $this;
    }

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function setHost(IcingaHost $host)
    {
        $this->clearHosts();
        return $this->addHost($host);
    }

    /**
     * @param IcingaHost[] $hosts
     * @return $this
     */
    public function setHosts(array $hosts)
    {
        $this->clearHosts();
        return $this->addHosts($hosts);
    }

    /**
     * @return $this
     */
    public function clearHosts()
    {
        $this->hosts = array();
        return $this;
    }

    /**
     * @param IcingaHostGroup $group
     * @return $this
     */
    public function addHostgroup(IcingaHostGroup $group)
    {
        $this->assertBeenLoadedFromDb($group);

        if ($group->get('assign_filter') !== null) {
            $this->hostgroups[$group->get('id')] = $group;
        }

        return $this;
    }

    /**
     * @param IcingaHostGroup[] $groups
     * @return $this
     */
    public function addHostgroups(array $groups)
    {
        foreach ($groups as $group) {
            $this->addHostgroup($group);
        }

        return $this;
    }

    /**
     * @param IcingaHostGroup $group
     * @return $this
     */
    public function setHostgroup(IcingaHostGroup $group)
    {
        $this->clearHostgroups();
        return $this->addHostgroup($group);
    }

    /**
     * @param array $groups
     * @return $this
     */
    public function setHostgroups(array $groups)
    {
        $this->clearHostgroups();
        return $this->addHostgroups($groups);
    }

    /**
     * @return $this
     */
    public function clearHostgroups()
    {
        $this->hosts = array();
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
                $this->table,
                $row
            );
        }

        $this->commit();
        Benchmark::measure(
            sprintf(
                'Stored %d new resolved hostgroup memberships',
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

        $db = $this->db;
        $this->beginTransaction();
        foreach ($diff as $row) {
            $db->delete(
                $this->table,
                sprintf(
                    '(hostgroup_id = %d AND host_id = %d)',
                    $row['hostgroup_id'],
                    $row['host_id']
                )
            );
        }

        $this->commit();
        Benchmark::measure(
            sprintf(
                'Removed %d outdated hostgroup memberships',
                $count
            )
        );
    }

    protected function getDifference(& $left, & $right)
    {
        $diff = array();

        foreach ($left as $groupId => $hostIds) {
            if (array_key_exists($groupId, $right)) {
                foreach ($hostIds as $hostId) {
                    if (! array_key_exists($hostId, $right[$groupId])) {
                        $diff[] = array(
                            'hostgroup_id' => $groupId,
                            'host_id'      => $hostId,
                        );
                    }
                }
            } else {
                foreach ($hostIds as $hostId) {
                    $diff[] = array(
                        'hostgroup_id' => $groupId,
                        'host_id'      => $hostId,
                    );
                }
            }
        }

        return $diff;
    }

    protected function fetchStoredMappings()
    {
        $mappings = array();

        $query = $this->db->select()->from(
            array('hgh' => $this->table),
            array(
                'hostgroup_id',
                'host_id',
            )
        );
        $this->addMembershipWhere($query, 'host_id', $this->hosts);
        $this->addMembershipWhere($query, 'hostgroup_id', $this->hostgroups);

        foreach ($this->db->fetchAll($query) as $row) {
            $groupId = $row->hostgroup_id;
            $hostId = $row->host_id;
            if (! array_key_exists($groupId, $mappings)) {
                $mappings[$groupId] = array();
            }

            $mappings[$groupId][$hostId] = $hostId;
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

    protected function recheckAllHosts($groups)
    {
        $mappings = array();

        foreach ($this->getHosts() as $host) {
            $resolver = HostApplyMatches::prepare($host);
            foreach ($groups as $groupId => $filter) {
                if ($resolver->matchesFilter(Filter::fromQueryString($filter))) {
                    if (! array_key_exists($groupId, $mappings)) {
                        $mappings[$groupId] = array();
                    }

                    $id = $host->get('id');
                    $mappings[$groupId][$id] = $id;
                }
            }
        }

        $this->newMappings = $mappings;
    }

    protected function getAppliedHostgroups()
    {
        if (empty($this->hostgroups)) {
            return $this->fetchAppliedHostgroups();
        } else {
            return $this->buildAppliedHostgroups();
        }
    }

    protected function buildAppliedHostgroups()
    {
        $list = array();
        foreach ($this->hostgroups as $id => $group) {
            $list[$id] = $group->get('assign_filter');
        }

        return $list;
    }

    protected function fetchAppliedHostgroups()
    {
        $query = $this->db->select()->from(
            array('hg' => 'icinga_hostgroup'),
            array(
                'id',
                'assign_filter',
            )
        )->where('assign_filter IS NOT NULL');

        return $this->db->fetchPairs($query);
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
     * @return IcingaHost[]
     */
    protected function getHosts()
    {
        if ($this->hosts === null) {
            $this->hosts = IcingaHost::loadAll($this->connection);
        }

        return $this->hosts;
    }

    protected function assertBeenLoadedFromDb(IcingaObject $object)
    {
        if (! ctype_digit($object->get('id'))) {
            throw new ProgrammingError(
                'Hostgroup resolver does not support unstored objects'
            );
        }
    }
}
