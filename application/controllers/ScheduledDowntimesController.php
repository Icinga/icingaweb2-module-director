<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ScheduledDowntimesController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        return $res;
    }

    public function getType()
    {
        return 'scheduledDowntime';
    }

    public function getBaseObjectUrl()
    {
        return 'scheduled-downtime';
    }
}
