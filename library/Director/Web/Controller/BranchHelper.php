<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Widget\NotInBranchedHint;

trait BranchHelper
{
    /** @var Branch */
    protected $branch;

    /** @var BranchStore */
    protected $branchStore;

    protected static $banchedTables = [
        'icinga_apiuser',
        'icinga_command',
        'icinga_dependency',
        'icinga_endpoint',
        'icinga_host',
        'icinga_hostgroup',
        'icinga_notification',
        'icinga_scheduled_downtime',
        'icinga_service',
        'icinga_servicegroup',
        'icinga_timeperiod',
        'icinga_user',
        'icinga_usergroup',
        'icinga_zone',
    ];

    /**
     * @return false|\Ramsey\Uuid\UuidInterface
     */
    protected function getBranchUuid()
    {
        return $this->getBranch()->getUuid();
    }

    protected function getBranch()
    {
        if ($this->branch === null) {
            /** @var ActionController $this */
            $this->branch = Branch::forRequest($this->getRequest(), $this->getBranchStore(), $this->Auth());
        }

        return $this->branch;
    }

    /**
     * @return BranchStore
     */
    protected function getBranchStore()
    {
        if ($this->branchStore === null) {
            $this->branchStore = new BranchStore($this->db());
        }

        return $this->branchStore;
    }

    protected function hasBranch()
    {
        return $this->getBranchUuid() !== null;
    }

    protected function tableHasBranchSupport($table)
    {
        return in_array($table, self::$banchedTables, true);
    }

    protected function enableStaticObjectLoader($table)
    {
        if ($this->tableHasBranchSupport($table)) {
            IcingaObject::setDbObjectStore(new DbObjectStore($this->db(), $this->getBranch()));
        }
    }

    /**
     * @param string $subject
     * @return bool
     */
    protected function showNotInBranch($subject)
    {
        if ($this->getBranch()->isBranch()) {
            $this->content()->add(new NotInBranchedHint($subject, $this->getBranch(), $this->Auth()));
            return true;
        }

        return false;
    }
}
