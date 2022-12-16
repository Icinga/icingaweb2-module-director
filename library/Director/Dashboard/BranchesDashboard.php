<?php

namespace Icinga\Module\Director\Dashboard;

use gipfl\Web\Widget\Hint;
use Icinga\Application\Hook;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Db\Branch\PreferredBranchSupport;
use Icinga\Module\Director\Hook\BranchSupportHook;
use ipl\Html\Html;

class BranchesDashboard extends Dashboard
{
    public function getTitle()
    {
        $branch = Branch::detect(new BranchStore($this->getDb()));
        if ($branch->isBranch()) {
            $this->prepend(Hint::info(Html::sprintf(
                $this->translate('You\'re currently working in a Configuration Branch: %s'),
                Branch::requireHook()->linkToBranch($branch, $this->getAuth(), $branch->getName())
            )));
        } else {
            if (($implementation = Branch::optionalHook()) && $implementation instanceof PreferredBranchSupport) {
                if ($implementation->hasPreferredBranch(Auth::getInstance())) {
                    $this->prepend(Hint::warning(
                        $this->translate('You\'re currently working in the main Configuration Branch'),
                    ));
                }
            }
        }

        return $this->translate('Prepare your configuration in a safe Environment');
    }

    public function loadDashlets()
    {
        /** @var BranchSupportHook $hook */
        if ($hook = Hook::first('director/BranchSupport')) {
            $this->dashlets = $hook->loadDashlets($this->getDb());
        } else {
            $this->dashlets = [];
        }
    }
}
