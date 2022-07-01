<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchStore
{
    const TABLE = 'director_branch';
    const TABLE_ACTIVITY = 'director_branch_activity';
    const OBJECT_TABLES = [
        'branched_icinga_apiuser',
        'branched_icinga_command',
        'branched_icinga_dependency',
        'branched_icinga_endpoint',
        'branched_icinga_host',
        'branched_icinga_hostgroup',
        'branched_icinga_notification',
        'branched_icinga_scheduled_downtime',
        'branched_icinga_service',
        'branched_icinga_service_set',
        'branched_icinga_servicegroup',
        'branched_icinga_timeperiod',
        'branched_icinga_user',
        'branched_icinga_usergroup',
        'branched_icinga_zone',
    ];

    protected $connection;

    protected $db;

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

    public function cloneBranchForSync(Branch $branch, $newName, $owner)
    {
        $this->runTransaction(function ($db) use ($branch, $newName, $owner) {
            $tables = self::OBJECT_TABLES;
            $tables[] = self::TABLE_ACTIVITY;
            $newBranch = $this->createBranchByName($newName, $owner);
            $oldQuotedUuid = DbUtil::quoteBinaryCompat($branch->getUuid()->getBytes(), $db);
            $quotedUuid = DbUtil::quoteBinaryCompat($newBranch->getUuid()->getBytes(), $db);
            // $timestampNs = (int)floor(microtime(true) * 1000000);
            // Hint: would love to do SELECT *, $quotedUuid AS branch_uuid FROM $table INTO $table
            foreach ($tables as $table) {
                $rows = $db->fetchAll($db->select()->from($table)->where('branch_uuid = ?', $oldQuotedUuid));
                foreach ($rows as $row) {
                    $modified = (array)$row;
                    $modified['branch_uuid'] = $quotedUuid;
                    if ($table === self::TABLE_ACTIVITY) {
                        $modified['timestamp_ns'] = round($modified['timestamp_ns'] / 1000000);
                    }
                    $db->insert($table, $modified);
                }
            }
        });

        return $this->fetchBranchByName($newName);
    }

    protected function runTransaction($callback)
    {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $callback($db);
            $db->commit();
        } catch (\Exception $e) {
            try {
                $db->rollBack();
            } catch (\Exception $ignored) {
                //
            }
            throw $e;
        }
    }

    public function wipeBranch(Branch $branch, $after = null)
    {
        $this->runTransaction(function ($db) use ($branch, $after) {
            $tables = self::OBJECT_TABLES;
            $tables[] = self::TABLE_ACTIVITY;
            $quotedUuid = DbUtil::quoteBinaryCompat($branch->getUuid()->getBytes(), $db);
            $where = $db->quoteInto('branch_uuid = ?', $quotedUuid);
            foreach ($tables as $table) {
                if ($after && $table === self::TABLE_ACTIVITY) {
                    $db->delete($table, $where . ' AND timestamp_ns > ' . (int) $after);
                } else {
                    $db->delete($table, $where);
                }
            }
        });

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
            ['ba' => self::TABLE_ACTIVITY],
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
        $this->db->insert(self::TABLE, $properties);

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
        return $this->db->delete(self::TABLE, $this->db->quoteInto(
            'uuid = ?',
            $this->connection->quoteBinary($uuid->getBytes())
        ));
    }

    /**
     * @param string $name
     * @return int
     */
    public function deleteByName($name)
    {
        return $this->db->delete(self::TABLE, $this->db->quoteInto(
            'branch_name = ?',
            $name
        ));
    }

    public function delete(Branch $branch)
    {
        return $this->deleteByUuid($branch->getUuid());
    }

    /**
     * @param Branch $branch
     * @param ?int $after
     * @return float|null
     */
    public function getLastActivityTime(Branch $branch, $after = null)
    {
        $db = $this->db;
        $query = $db->select()
            ->from(self::TABLE_ACTIVITY, 'MAX(timestamp_ns)')
            ->where('branch_uuid = ?', DbUtil::quoteBinaryCompat($branch->getUuid()->getBytes(), $db));
        if ($after) {
            $query->where('timestamp_ns > ?', (int) $after);
        }

        $last = $db->fetchOne($query);
        if ($last) {
            return $last / 1000000;
        }

        return null;
    }
}
