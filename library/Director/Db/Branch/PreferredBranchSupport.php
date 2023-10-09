<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Authentication\Auth;

interface PreferredBranchSupport
{
    public function hasPreferredBranch(Auth $auth): bool;
}
