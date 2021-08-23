<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Db;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchStore
{
    protected $connection;

    protected $db;

    protected $table = 'director_branch';

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    /**
     * @param UuidInterface $uuid
     * @return ?Branch
     */
    public function fetchBranchByUuid(UuidInterface $uuid)
    {
        return $this->newFromDbResult(
            $this->select()->where('b.uuid = ?', $this->connection->quoteBinary($uuid->getBytes()))
        );
    }

    /**
     * @param string $name
     * @return ?Branch
     */
    public function fetchBranchByName($name)
    {
        return $this->newFromDbResult($this->select()->where('b.branch_name = ?', $name));
    }

    protected function newFromDbResult($query)
    {
        if ($row = $this->db->fetchRow($query)) {
            return Branch::fromDbRow($row);
        }

        return null;
    }

    public function setReadyForMerge(Branch $branch)
    {
        $update = [
            'should_be_merged' => 'y'
        ];

        $name = $branch->getName();
        if (preg_match('#^/enforced/(.+)$#', $name, $match)) {
            $update['branch_name'] = '/merge/' . substr(sha1($branch->getUuid()->getBytes()), 0, 7) . '/' . $match[1];
        }
        $this->db->update('director_branch', $update, $this->db->quoteInto(
            'uuid = ?',
            $this->connection->quoteBinary($branch->getUuid()->getBytes())
        ));
    }

    protected function select()
    {
        return $this->db->select()->from(['b' => 'director_branch'], [
            'uuid'             => 'b.uuid',
            'owner'            => 'b.owner',
            'branch_name'      => 'b.branch_name',
            'description'      => 'b.description',
            'should_be_merged' => 'b.should_be_merged',
            'cnt_activities'   => 'COUNT(ba.change_time)',
        ])->joinLeft(
            ['ba' => 'director_branch_activity'],
            'b.uuid = ba.branch_uuid',
            []
        )->group('b.uuid');
    }

    /**
     * @param string $name
     * @return Branch
     * @throws \Zend_Db_Adapter_Exception
     */
    public function fetchOrCreateByName($name, $owner)
    {
        if ($branch = $this->fetchBranchByName($name)) {
            return $branch;
        }

        return $this->createBranchByName($name, $owner);
    }

    /**
     * @param string $branchName
     * @param string $owner
     * @return Branch
     * @throws \Zend_Db_Adapter_Exception
     */
    public function createBranchByName($branchName, $owner)
    {
        $this->uuid = Uuid::uuid4();
        $properties = [
            'uuid'        => $this->uuid->getBytes(),
            'branch_name' => $branchName,
            'owner'       => $owner,
            'description' => null,
            'should_be_merged' => 'n',
        ];
        $this->db->insert($this->table, $properties);

        return Branch::fromDbRow((object) $properties);
    }
}
