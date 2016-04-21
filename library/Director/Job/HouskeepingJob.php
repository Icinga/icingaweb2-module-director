<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Db\Housekeeping;
use Icinga\Module\Director\Hook\JobHook;

class HousekeepingJob extends JobHook
{
    protected $housekeeping;

    public function run()
    {
        $this->housekeeping()->runAllTasks();
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
