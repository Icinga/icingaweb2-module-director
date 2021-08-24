<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\InvalidDataException;
use Icinga\Module\Director\Db;
use Ramsey\Uuid\Uuid;
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
    protected $ignoreUuids = [];

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
     * @param array $uuids
     */
    public function ignoreUuids(array $uuids)
    {
        foreach ($uuids as $uuid) {
            $this->ignoreUuid($uuid);
        }
    }

    /**
     * @param UuidInterface|string $uuid
     */
    public function ignoreUuid($uuid)
    {
        if (is_string($uuid)) {
            $uuid = Uuid::fromString($uuid);
        } elseif (! ($uuid instanceof UuidInterface)) {
            throw new InvalidDataException('UUID', $uuid);
        }
        $binary = $uuid->getBytes();
        $this->ignoreUuids[$binary] = $binary;
    }

    /**
     * @throws MergeError
     * @throws \Exception
     */
    public function merge()
    {
        $this->connection->runFailSafeTransaction(function () {
            $activities = new BranchActivityStore($this->connection);
            $rows = $activities->loadAll($this->branchUuid);
            foreach ($rows as $row) {
                $modification = BranchActivityStore::objectModificationForDbRow($row);
                $this->applyModification($modification, Uuid::fromBytes($row->uuid));
            }
            $this->db->delete('director_branch', $this->db->quoteInto('uuid = ?', $this->branchUuid->getBytes()));
        });
    }

    /**
     * @param ObjectModification $modification
     * @param UuidInterface $uuid
     * @throws MergeError
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function applyModification(ObjectModification $modification, UuidInterface $uuid)
    {
        $binaryUuid = $uuid->getBytes();
        /** @var string|DbObject $class */
        $class = $modification->getClassName();
        $keyParams = (array) $modification->getKeyParams();
        if (array_keys($keyParams) === ['object_name']) {
            $keyParams = $keyParams['object_name'];
        }

        $exists = $class::exists($keyParams, $this->connection);
        if ($modification->isCreation()) {
            if ($exists) {
                if (! isset($this->ignoreUuids[$uuid->getBytes()])) {
                    throw new MergeErrorRecreateOnMerge($modification, $uuid);
                }
            } else {
                $object = IcingaObjectModification::applyModification($modification, null, $this->connection);
                $object->store($this->connection);
            }
        } elseif ($modification->isDeletion()) {
            if ($exists) {
                $object = IcingaObjectModification::applyModification($modification, $class::load($keyParams, $this->connection), $this->connection);
                $object->setConnection($this->connection);
                $object->delete();
            } elseif (! $this->ignoreDeleteWhenMissing && ! isset($this->ignoreUuids[$binaryUuid])) {
                throw new MergeErrorDeleteMissingObject($modification, $uuid);
            }
        } else {
            if ($exists) {
                $object = IcingaObjectModification::applyModification($modification, $class::load($keyParams, $this->connection), $this->connection);
                // TODO: du änderst ein Objekt, und die geänderte Eigenschaften haben sich seit der Änderung geändert
                $object->store($this->connection);
            } elseif (! $this->ignoreModificationWhenMissing && ! isset($this->ignoreUuids[$binaryUuid])) {
                throw new MergeErrorModificationForMissingObject($modification, $uuid);
            }
        }
    }
}
