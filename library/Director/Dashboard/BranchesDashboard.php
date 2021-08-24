<?php

namespace Icinga\Module\Director\Dashboard;

use gipfl\IcingaWeb2\Icon;
use Icinga\Application\Hook;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchStore;
use Icinga\Module\Director\Hook\BranchSupportHook;
use ipl\Html\Html;

class BranchesDashboard extends Dashboard
{
    public function getTitle()
    {
        $branch = Branch::detect(new BranchStore($this->getDb()));
        if ($branch->isBranch()) {
            return Html::sprintf(
                $this->translate('Working in a Configuration Branch: %s'),
                Html::tag('span', ['class' => 'active-branch'], $branch->getName())
            );
        }

        return $this->translate('Prepare your configuration in a safe Environment');
    }

    public function loadDashlets()
    {
        /** @var BranchSupportHook $hook */
        if ($hook = Hook::first('director/BranchSupport')) {
            $this->dashlets = $hook->loadDashlets($this->getDb());
        }
    }
}
