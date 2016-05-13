<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Db\Housekeeping;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class HousekeepingJob extends JobHook
{
    protected $housekeeping;

    public function run()
    {
        $this->housekeeping()->runAllTasks();
    }

    public static function getDescription(QuickForm $form)
    {
        return $form->translate(
            'The Housekeeping job provides various task that keep your Director'
            . ' database fast and clean'
        );
    }

    public function isPending()
    {
        return $this->housekeeping()->hasPendingTasks();
    }

    protected function housekeeping()
    {
        if ($this->housekeeping === null) {
            $this->housekeeping = new Housekeeping($this->db());
        }

        return $this->housekeeping;
    }
}
