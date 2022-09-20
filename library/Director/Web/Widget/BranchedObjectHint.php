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

    public function __construct(Branch $branch, Auth $auth, BranchedObject $object = null)
    {
        if (! $branch->isBranch()) {
            return;
        }
        $hook = Branch::requireHook();

        $name = $branch->getName();
        if (substr($name, 0, 1) === '/') {
            $label = $this->translate('this configuration branch');
        } else {
            $label = $name;
        }
        $link = $hook->linkToBranch($branch, $auth, $label);
        if ($object === null) {
            $this->add(Hint::info(Html::sprintf($this->translate(
                'This object will be created in %s. It will not be part of any deployment'
                . ' unless being merged'
            ), $link)));
            return;
        }

        if (! $object->hasBeenTouchedByBranch()) {
            $this->add(Hint::info(Html::sprintf($this->translate(
                'Your changes will be stored in %s. The\'ll not be part of any deployment'
                . ' unless being merged'
            ), $link)));
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
