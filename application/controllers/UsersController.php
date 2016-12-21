<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class UsersController extends ObjectsController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/users');
    }
}
