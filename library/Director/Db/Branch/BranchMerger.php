<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\CustomVariable\PropertyDetachmentCleaner;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
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
    public function merge($comment = null)
    {
        $username = DirectorActivityLog::username();
        $this->connection->runFailSafeTransaction(function () use ($comment, $username) {
            $formerActivityId = (int) DirectorActivityLog::loadLatest($this->connection)->get('id');
            $query = $this->db->select()
                ->from(BranchActivity::DB_TABLE)
                ->where('branch_uuid = ?', $this->connection->quoteBinary($this->branchUuid->getBytes()))
                ->order('timestamp_ns ASC');
            $rows = $this->db->fetchAll($query);
            foreach ($rows as $row) {
                $activity = BranchActivity::fromDbRow($row);
                $author = $activity->getAuthor();
                if ($username !== $author) {
                    DirectorActivityLog::overrideUsername("$author/$username");
                }
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
        DirectorActivityLog::restoreUsername();
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
                $activity->createDbObject($this->connection)->store($this->connection);
            }
        } elseif ($activity->isActionDelete()) {
            if ($exists) {
                $activity->deleteDbObject($class::requireWithUniqueId($uuid, $this->connection));
            } elseif (! $this->ignoreDeleteWhenMissing && ! $this->ignoresActivity($activity)) {
                throw new MergeErrorDeleteMissingObject($activity);
            }
        } else {
            if ($exists) {
                $object = $activity->applyToDbObject($class::requireWithUniqueId($uuid, $this->connection));
                // Note the dropped imports before storing, it overwrites them
                $removedImportNames = $this->removedImportNames($object);
                $object->store();

                // The branch only staged the import change, clean up the values it orphaned now
                if (! empty($removedImportNames)) {
                    PropertyDetachmentCleaner::removeValuesLostToRemovedImports(
                        $object,
                        $removedImportNames,
                        $this->connection
                    );
                }

                // TODO: you modified an object, and related properties have been changed in the meantime.
                //       We're able to detect this with the given data, and might want to offer a rebase.
            } elseif (! $this->ignoreModificationWhenMissing && ! $this->ignoresActivity($activity)) {
                throw new MergeErrorModificationForMissingObject($activity);
            }
        }
    }

    /**
     * Imports the object had in the database but no longer has
     *
     * @param DbObject $object
     *
     * @return string[]
     */
    protected function removedImportNames(DbObject $object): array
    {
        if (
            ! $object instanceof IcingaObject
            || ! $object->supportsImports()
            || ! $object->supportsCustomVars()
        ) {
            return [];
        }

        return array_diff($object->imports()->listOriginalImportNames(), $object->getImports());
    }
}
