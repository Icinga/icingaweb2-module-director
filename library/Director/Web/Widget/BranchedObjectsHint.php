<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db\Branch\Branch;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class BranchedObjectsHint extends HtmlDocument
{
    use TranslationHelper;

    public function __construct(Branch $branch, Auth $auth, $hasPreferredBranch = false)
    {
        if (! $branch->isBranch()) {
            if ($hasPreferredBranch) {
                $this->add(Hint::warning($this->translate(
                    "You're currently in the master branch, your changes will make part of the next Deployment"
                )));
            }
            return;
        }
        $hook = Branch::requireHook();
        $this->add(Hint::info(Html::sprintf(
            $this->translate('Showing a branched view, with potential changes being visible only in this %s'),
            $hook->linkToBranch($branch, $auth, $this->translate('configuration branch'))
        )));
    }
}
