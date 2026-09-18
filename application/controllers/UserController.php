<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
