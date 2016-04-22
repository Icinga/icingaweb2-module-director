<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class SyncJob extends JobHook
{
    public function run()
    {
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The "Sync" job allows to run sync actions at regular intervals'
        );
    }

    public function isPending()
    {
    }
}
