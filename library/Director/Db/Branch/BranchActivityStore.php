<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Json;
use Icinga\Module\Director\Db;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchActivityStore
{
    protected $connection;

    protected $db;

    protected $table = 'director_branch_activity';

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function count(UuidInterface $branchUuid)
    {
        $query = $this->db->select()
            ->from($this->table, ['cnt' => 'COUNT(*)'])
            ->where('branch_uuid = ?', $branchUuid->getBytes());

        return (int) $this->db->fetchOne($query);
    }

    public function loadAll(UuidInterface $branchUuid)
    {
        $query = $this->db->select()
            ->from($this->table)
            ->where('branch_uuid = ?', $branchUuid->getBytes())
            ->order('change_time DESC');
        return $this->db->fetchAll($query);
    }

    public static function objectModificationForDbRow($row)
    {
        $modification = ObjectModification::fromSerialization(json_decode($row->change_set));
        return $modification;
    }

    /**
     * Must be run in a transaction!
     *
     * @param ObjectModification $modification
     * @param UuidInterface $branchUuid
     * @throws \Icinga\Module\Director\Exception\JsonEncodeException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function persistModification(ObjectModification $modification, UuidInterface $branchUuid)
    {
        $db = $this->db;
        $last = $db->fetchOne(
            $db->select()
                ->from('director_branch_activity', 'checksum')
                ->order('change_time DESC')
                ->order('uuid') // Just in case, this gives a guaranteed order
        );
        // TODO: eventually implement more checks, allow only one change per millisecond
        //       alternatively use last change_time plus one, when now < change_time
        if (strlen($last) !== 20) {
            $last = '';
        }
        $binaryUuid = Uuid::uuid4()->getBytes();
        $timestampMs = $this->now();
        $encoded =  Json::encode($modification);

        // HINT: checksums are useless! -> merge only
        $this->db->insert('director_branch_activity', [
            'uuid'            => $binaryUuid,
            'branch_uuid'     => $branchUuid->getBytes(),
            'change_set'      => $encoded,  // TODO: rename -> object_modification
            'change_time'     => $timestampMs, // TODO: ns!!
            'checksum'        => sha1("$last/$binaryUuid/$timestampMs/$encoded", true),
            'parent_checksum' => $last === '' ? null : $last,
        ]);
    }

    protected function now()
    {
        return floor(microtime(true) * 1000);
    }
}
