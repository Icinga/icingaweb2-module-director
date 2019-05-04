<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class GroupMembershipCache
{
    protected $type;

    protected $table;

    protected $groupClass;

    protected $memberships;

    /** @var Db Director database connection */
    protected $connection;

    public function __construct(IcingaObject $object)
    {
        $this->table = $object->getTableName();
        $this->type  = $object->getShortTableName();

        $this->groupClass = 'Icinga\\Module\\Director\\Objects\\Icinga'
            . ucfirst($this->type) . 'Group';

        Benchmark::measure('Initializing GroupMemberShipCache');
        $this->connection = $object->getConnection();
        $this->loadAllMemberships();
        Benchmark::measure('Filled GroupMemberShipCache');
    }

    protected function loadAllMemberships()
    {
        $db = $this->connection->getDbAdapter();
        $this->memberships = array();

        $type  = $this->type;
        $table = $this->table;

        $query = $db->select()->from(
            array('o' => $table),
            array(
                'object_id'   => 'o.id',
                'group_id'    => 'g.id',
                'group_name'  => 'g.object_name',
            )
        )->join(
            array('go' => $table . 'group_' . $type),
            'o.id = go.' . $type . '_id',
            array()
        )->join(
            array('g' => $table . 'group'),
            'go.' . $type . 'group_id = g.id',
            array()
        )->order('g.object_name');

        foreach ($db->fetchAll($query) as $row) {
            if (! array_key_exists($row->object_id, $this->memberships)) {
                $this->memberships[$row->object_id] = array();
            }

            $this->memberships[$row->object_id][$row->group_id] = $row->group_name;
        }
    }

    public function listGroupNamesForObject(IcingaObject $object)
    {
        if (array_key_exists($object->id, $this->memberships)) {
            return array_values($this->memberships[$object->id]);
        }

        return array();
    }

    public function listGroupIdsForObject(IcingaObject $object)
    {
        if (array_key_exists($object->id, $this->memberships)) {
            return array_keys($this->memberships[$object->id]);
        }

        return array();
    }

    public function getGroupsForObject(IcingaObject $object)
    {
        $groups = array();
        $class = $this->groupClass;
        foreach ($this->listGroupIdsForObject($object) as $id) {
            $object = $class::loadWithAutoIncId($id, $this->connection);
            $groups[$object->object_name] = $object;
        }

        return $groups;
    }

    public function __destruct()
    {
        unset($this->connection);
    }
}
