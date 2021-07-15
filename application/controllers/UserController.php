<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class UserController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/users');
    }

    protected function hasBasketSupport()
    {
        return true;
    }
}
