<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchModificationStore;
use Icinga\Module\Director\Db\Branch\IcingaObjectModification;
use function in_array;

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

    /** @var Branch */
    protected $branch;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
    }

    public function setBranch(Branch $branch)
    {
        $this->branch = $branch;
    }

    protected function typeSupportsBranches($type)
    {
        return in_array($type, ['host', 'user', 'zone', 'timeperiod']);
    }

    /**
     * @param string $shortType
     * @param string|array $key
     * @return DbObject
     * @throws NotFoundError
     */
    public function load($shortType, $key)
    {
        return $this->loadWithBranchModification($shortType, $key)[0];
    }

    /**
     * @param string $shortType
     * @param string|array $key
     * @return array
     * @throws NotFoundError
     */
    public function loadWithBranchModification($shortType, $key)
    {
        /** @var string|DbObject $class */
        $class = DbObjectTypeRegistry::classByType($shortType);
        if ($this->branch && $this->branch->isBranch() && $this->typeSupportsBranches($shortType) && is_string($key)) {
            $branchStore = new BranchModificationStore($this->connection, $shortType);
        } else {
            $branchStore = null;
        }
        $modification = null;
        try {
            $object = $class::load($key, $this->connection);
            if ($branchStore && $modification = $branchStore->eventuallyLoadModification(
                    $object->get('id'),
                    $this->branch->getUuid()
                )) {
                $object = IcingaObjectModification::applyModification($modification, $object, $this->connection);
            }
        } catch (NotFoundError $e) {
            if ($this->branch  && $this->branch->isBranch() && is_string($key)) {
                $branchStore = new BranchModificationStore($this->connection, $shortType);
                $modification = $branchStore->loadOptionalModificationByName($key, $this->branch->getUuid());
                if ($modification) {
                    $object = IcingaObjectModification::applyModification($modification, null, $this->connection);
                    $object->setConnection($this->connection);
                    if ($id = $object->get('id')) { // Object has probably been renamed
                        try {
                            // TODO: can be one step I guess, but my brain is slow today ;-)
                            $renamedObject = $class::load($id, $this->connection);
                            $object = IcingaObjectModification::applyModification(
                                $modification,
                                $renamedObject,
                                $this->connection
                            );
                        } catch (NotFoundError $e) {
                            // Well... it was worth trying
                            $object->setConnection($this->connection);
                            $object->setBeingLoadedFromDb();
                        }
                    } else {
                        $object->setConnection($this->connection);
                        $object->setBeingLoadedFromDb();
                    }
                } else {
                    throw $e;
                }
            } else {
                throw $e;
            }
        }

        return [$object, $modification];
    }
}
