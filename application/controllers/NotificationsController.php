<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Web\Controller\ObjectsController;

class NotificationsController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        return $res;
    }

    public function indexAction()
    {
        throw new NotFoundError('Not found');
    }

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/notifications');
    }
}
