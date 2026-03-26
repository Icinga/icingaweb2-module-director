<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class CommandObjectDashlet extends Dashlet
{
    protected $icon = 'wrench';

    protected $requiredStats = ['command'];

    public function getTitle()
    {
        return $this->translate('Commands');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=commands';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
