<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Ramsey\Uuid\UuidInterface;

class BranchMerger
{
    /** @var Branch */
    protected $branchUuid;

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var array */
    protected $ignoreActivities = [];

    /** @var bool */
    protected $ignoreDeleteWhenMissing = false;

    /** @var bool */
    protected $ignoreModificationWhenMissing = false;

    /**
     * Apply branch modifications
     *
     * TODO: allow to skip or ignore modifications, in case modified properties have
     * been changed in the meantime
     *
     * @param UuidInterface $branchUuid
     * @param Db $connection
     */
    public function __construct(UuidInterface $branchUuid, Db $connection)
    {
        $this->branchUuid = $branchUuid;
        $this->db = $connection->getDbAdapter();
        $this->connection = $connection;
    }

    /**
     * Skip a delete operation, when the object to be deleted does not exist
     *
     * @param bool $ignore
     */
    public function ignoreDeleteWhenMissing($ignore = true)
    {
        $this->ignoreDeleteWhenMissing = $ignore;
    }

    /**
     * Skip a modification, when the related object does not exist
     * @param bool $ignore
     */
    public function ignoreModificationWhenMissing($ignore = true)
    {
        $this->ignoreModificationWhenMissing = $ignore;
    }

    /**
     * @param int $key
     */
    public function ignoreActivity($key)
    {
        $this->ignoreActivities[$key] = true;
    }

    /**
     * @param BranchActivity $activity
     * @return bool
     */
    public function ignoresActivity(BranchActivity $activity)
    {
        return isset($this->ignoreActivities[$activity->getTimestampNs()]);
    }

    /**
     * @throws MergeError
     */
    public function merge($comment)
    {
        $this->connection->runFailSafeTransaction(function () use ($comment) {
            $formerActivityId = (int) DirectorActivityLog::loadLatest($this->connection)->get('id');
            $query = $this->db->select()
                ->from(BranchActivity::DB_TABLE)
                ->where('branch_uuid = ?', $this->connection->quoteBinary($this->branchUuid->getBytes()))
                ->order('timestamp_ns ASC');
            $rows = $this->db->fetchAll($query);
            foreach ($rows as $row) {
                $activity = BranchActivity::fromDbRow($row);
                $this->applyModification($activity);
            }
            (new BranchStore($this->connection))->deleteByUuid($this->branchUuid);
            $currentActivityId = (int) DirectorActivityLog::loadLatest($this->connection)->get('id');
            $firstActivityId = (int) $this->db->fetchOne(
                $this->db->select()->from('director_activity_log', 'MIN(id)')->where('id > ?', $formerActivityId)
            );
            if ($comment && strlen($comment)) {
                $this->db->insert('director_activity_log_remark', [
                    'first_related_activity' => $firstActivityId,
                    'last_related_activity' => $currentActivityId,
                    'remark' => $comment,
                ]);
            }
        });
    }

    /**
     * @param BranchActivity $activity
     * @throws MergeError
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function applyModification(BranchActivity $activity)
    {
        /** @var string|DbObject $class */
        $class = DbObjectTypeRegistry::classByType($activity->getObjectTable());
        $uuid = $activity->getObjectUuid();

        $exists = $class::uniqueIdExists($uuid, $this->connection);
        if ($activity->isActionCreate()) {
            if ($exists) {
                if (! $this->ignoresActivity($activity)) {
                    throw new MergeErrorRecreateOnMerge($activity);
                }
            } else {
                $activity->createDbObject()->store($this->connection);
            }
        } elseif ($activity->isActionDelete()) {
            if ($exists) {
                $activity->deleteDbObject($class::requireWithUniqueId($uuid, $this->connection));
            } elseif (! $this->ignoreDeleteWhenMissing && ! $this->ignoresActivity($activity)) {
                throw new MergeErrorDeleteMissingObject($activity);
            }
        } else {
            if ($exists) {
                $activity->applyToDbObject($class::requireWithUniqueId($uuid, $this->connection))->store();
                // TODO: you modified an object, and related properties have been changed in the meantime.
                //       We're able to detect this with the given data, and might want to offer a rebase.
            } elseif (! $this->ignoreModificationWhenMissing && ! $this->ignoresActivity($activity)) {
                throw new MergeErrorModificationForMissingObject($activity);
            }
        }
    }
}
