<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchActivity;
use Icinga\Module\Director\Db\Branch\BranchedObject;

/**
 * Loader for Icinga/DbObjects
 *
 * Is aware of branches and prefetching. I would prefer to see a StoreInterface,
 * with one of the above wrapping the other. But for now, this helps to clean things
 * up
 */
class DbObjectStore
{
    /** @var Db */
    protected $connection;

    /** @var ?Branch */
    protected $branch;

    public function __construct(Db $connection, Branch $branch = null)
    {
        $this->connection = $connection;
        $this->branch = $branch;
    }

    public function store(DbObject $object)
    {
        if ($this->branch && $this->branch->isBranch()) {
            $activity = BranchActivity::forDbObject($object, $this->branch);
            $this->connection->runFailSafeTransaction(function () use ($activity) {
                $activity->store($this->connection);
                BranchedObject::withActivity($activity, $this->connection)->store($this->connection);
            });

            return true;
        } else {
            return $object->store($this->connection);
        }
    }

    public function delete(DbObject $object)
    {
        if ($this->branch && $this->branch->isBranch()) {
            $activity = BranchActivity::deleteObject($object, $this->branch);
            $this->connection->runFailSafeTransaction(function () use ($activity) {
                $activity->store($this->connection);
                BranchedObject::load(
                    $this->connection,
                    $activity->getObjectTable(),
                    $activity->getObjectUuid(),
                    $this->branch
                )->delete($this->connection);
            });
            return true;
        }

        return $object->delete();
    }
}
