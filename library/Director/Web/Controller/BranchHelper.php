<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Web\Widget\NotInBranchedHint;

trait BranchHelper
{
    /** @var Branch */
    protected $branch;

    /** @var BranchStore */
    protected $branchStore;

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
