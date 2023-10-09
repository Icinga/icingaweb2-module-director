<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\BranchedObject;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class BranchedObjectHint extends HtmlDocument
{
    use TranslationHelper;

    public function __construct(Branch $branch, Auth $auth, BranchedObject $object = null, $hasPreferredBranch = false)
    {
        if (! $branch->isBranch()) {
            if ($hasPreferredBranch) {
                $main = true;
                $hintMethod = 'warning';
                $link = $this->translate('the main configuration branch');
                $deployHint = ' ' . $this->translate('This will be part of the next deployment');
            } else {
                return;
            }
        } else {
            $main = false;
            $hintMethod = 'info';
            $deployHint = ' ' . $this->translate('This will not be part of any deployment, unless being merged');
            $hook = Branch::requireHook();
            $name = $branch->getName();
            if (substr($name, 0, 1) === '/') {
                $label = $this->translate('this configuration branch');
            } else {
                $label = $name;
            }
            $link = $hook->linkToBranch($branch, $auth, $label);
        }

        if ($object === null) {
            $this->add(Hint::$hintMethod(Html::sprintf($this->translate(
                'This object will be created in %s.'
            ) . $deployHint, $link)));
            return;
        }

        if (! $object->hasBeenTouchedByBranch()) {
            $this->add(Hint::$hintMethod(Html::sprintf($this->translate(
                'Your changes are going to be stored in %s.'
            ) . $deployHint, $link)));
            return;
        }
        if ($main) {
            return;
        }

        if ($object->hasBeenDeletedByBranch()) {
            throw new NotFoundError('No such object available');
            // Alternative, requires hiding other actions:
            // $this->add(Hint::info(Html::sprintf(
            //     $this->translate('This object has been deleted in %s'),
            //     $link
            // )));
        } elseif ($object->hasBeenCreatedByBranch()) {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('This object has been created in %s'),
                $link
            )));
        } else {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('This object has modifications visible only in %s'),
                // TODO: Also link to object modifications
                // $hook->linkToBranchedObject($this->translate('modifications'), $branch, $object, $auth),
                $link
            )));
        }
    }
}
