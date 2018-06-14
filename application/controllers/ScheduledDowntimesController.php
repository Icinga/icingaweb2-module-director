<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ScheduledDowntimesController extends ObjectsController
{
    public function getType()
    {
        return 'scheduledDowntime';
    }

    public function getBaseObjectUrl()
    {
        return 'scheduled-downtime';
    }
}
