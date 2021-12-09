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
            if (is_resource($row->uuid)) {
                $row->uuid = stream_get_contents($row->uuid);
            }
            return Branch::fromDbRow($row);
        }

        return null;
    }

    public function setReadyForMerge(Branch $branch)
    {
        $update = [
            'ts_merge_request' => (int) floor(microtime(true) * 1000000)
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
            'ts_merge_request' => 'b.ts_merge_request',
            'cnt_activities'   => 'COUNT(ba.timestamp_ns)',
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
        $uuid = Uuid::uuid4();
        $properties = [
            'uuid'        => $this->connection->quoteBinary($uuid->getBytes()),
            'branch_name' => $branchName,
            'owner'       => $owner,
            'description' => null,
            'ts_merge_request' => null,
        ];
        $this->db->insert($this->table, $properties);

        if ($branch = static::fetchBranchByUuid($uuid)) {
            return $branch;
        }

        throw new \RuntimeException(sprintf(
            'Branch with UUID=%s has been created, but could not be fetched from DB',
            $uuid->toString()
        ));
    }

    public function deleteByUuid(UuidInterface $uuid)
    {
        return $this->db->delete($this->table, $this->db->quoteInto(
            'uuid = ?',
            $this->connection->quoteBinary($uuid->getBytes())
        ));
    }

    public function delete(Branch $branch)
    {
        return $this->deleteByUuid($branch->getUuid());
    }
}
