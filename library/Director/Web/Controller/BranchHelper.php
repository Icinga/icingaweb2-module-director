<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Db\Branch\BranchSupport;
use Icinga\Module\Director\Db\Branch\PreferredBranchSupport;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Widget\NotInBranchedHint;
use Ramsey\Uuid\UuidInterface;

trait BranchHelper
{
    /** @var Branch */
    protected $branch;

    /** @var BranchStore */
    protected $branchStore;

    /** @var ?bool */
    protected $hasPreferredBranch = null;

    /**
     * @return ?UuidInterface
     */
    protected function getBranchUuid(): ?UuidInterface
    {
        return $this->getBranch()->getUuid();
    }

    /**
     * @return Branch
     */
    protected function getBranch(): Branch
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
    protected function getBranchStore(): BranchStore
    {
        if ($this->branchStore === null) {
            $this->branchStore = new BranchStore($this->db());
        }

        return $this->branchStore;
    }

    /**
     * @return bool
     */
    protected function hasBranch(): bool
    {
        return $this->getBranchUuid() !== null;
    }

    protected function enableStaticObjectLoader($table): void
    {
        if (BranchSupport::existsForTableName($table)) {
            IcingaObject::setDbObjectStore(new DbObjectStore($this->db(), $this->getBranch()));
        }
    }

    /**
     * @param string $subject
     * @return bool
     */
    protected function showNotInBranch($subject): bool
    {
        if ($this->getBranch()->isBranch()) {
            $this->content()->add(new NotInBranchedHint($subject, $this->getBranch(), $this->Auth()));
            return true;
        }

        return false;
    }

    protected function hasPreferredBranch(): bool
    {
        if ($this->hasPreferredBranch === null) {
            $implementation = Branch::optionalHook();
            if ($implementation instanceof PreferredBranchSupport) {
                $this->hasPreferredBranch = $implementation->hasPreferredBranch($this->Auth());
            } else {
                $this->hasPreferredBranch = false;
            }
        }

        return $this->hasPreferredBranch;
    }
}
