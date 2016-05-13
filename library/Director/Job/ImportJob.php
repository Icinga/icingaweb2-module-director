<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class ImportJob extends JobHook
{
    public function run()
    {
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The "Import" job allows to run import actions at regular intervals'
        );
    }

    public function isPending()
    {
    }
}
