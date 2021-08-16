<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Web\Request;

abstract class BranchSupportHook
{
    abstract public function getBranchForRequest(Request $request);

    abstract public function linkToBranch(Branch $branch, $label = null);

    abstract public function linkToBranchedObject($label, Branch $branch, DbObject $object);
}
