<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Db\Branch\Branch;
use Ramsey\Uuid\UuidInterface;

trait BranchHelper
{
    /** @var Branch */
    protected $branch;

    /** @var UuidInterface|null */
    protected $branchUuid;

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
            $this->branch = Branch::forRequest($this->getRequest());
        }

        return $this->branch;
    }

    protected function hasBranch()
    {
        return $this->getBranchUuid() !== null;
    }
}
