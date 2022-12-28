<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchSTore;
use Icinga\Web\Request;
use ipl\Html\ValidHtml;

abstract class BranchSupportHook
{
    /**
     * @param Request $request
     * @param BranchStore $store
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
     * @param Db $db
     * @return Dashlet[]
     */
    public function loadDashlets(Db $db)
    {
        return [];
    }
}
