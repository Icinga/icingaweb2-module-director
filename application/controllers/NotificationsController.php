<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\NewObjectsController;

class NotificationsController extends NewObjectsController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/notifications');
    }
}
