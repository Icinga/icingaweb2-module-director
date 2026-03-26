<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class DependenciesController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        return $res;
    }
}
