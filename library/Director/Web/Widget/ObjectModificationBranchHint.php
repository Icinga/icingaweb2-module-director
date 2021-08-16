<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\Branch\ObjectModification;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class ObjectModificationBranchHint extends HtmlDocument
{
    use TranslationHelper;

    public function __construct(Branch $branch, DbObject $object, ObjectModification $modification = null)
    {
        if (! $branch->isBranch()) {
            return;
        }
        $hook = Branch::requireHook();

        if ($modification === null) {
            $this->add(Hint::info($this->translate(
                'Your changes will be stored in an isolated branch. The\'ll not be part of any deployment'
                . ' unless being merged'
            )));
            return;
        }

        if ($modification->isDeletion()) {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('This object has been deleted in this configuration %s'),
                $hook->linkToBranch($branch, $this->translate('branch'))
            )));
        } elseif ($modification->isModification()) {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('This object has %s visible only in this configuration %s'),
                $hook->linkToBranchedObject($this->translate('modifications'), $branch, $object),
                $hook->linkToBranch($branch, $this->translate('branch'))
            )));
        } else {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('This object has been %s in this configuration %s'),
                $hook->linkToBranchedObject($this->translate('created'), $branch, $object),
                $hook->linkToBranch($branch, $this->translate('branch'))
            )));
        }
    }
}
