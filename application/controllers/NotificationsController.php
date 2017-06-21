<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class NotificationsController extends ObjectsController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/notifications');
    }
}
