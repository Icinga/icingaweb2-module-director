<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchSTore;
use Icinga\Web\Request;
use ipl\Html\ValidHtml;

abstract class BranchSupportHook
{
    /**
     * @param Request $request
     * @param BranchSTore $store
     * @param Auth $auth
     * @return Branch
     */
    abstract public function getBranchForRequest(Request $request, BranchStore $store, Auth $auth);

    /**
     * @param Branch $branch
     * @param Auth $auth
     * @param ?string $label
     * @return ?ValidHtml
     */
    abstract public function linkToBranch(Branch $branch, Auth $auth, $label = null);

    /**
     * @param string $label
     * @param Branch $branch
     * @param DbObject $object
     * @param Auth $auth
     * @return ?ValidHtml
     */
    abstract public function linkToBranchedObject($label, Branch $branch, DbObject $object, Auth $auth);

    /**
     * @param Db $db
     * @return Dashlet[]
     */
    public function loadDashlets(Db $db)
    {
        return [];
    }
}
