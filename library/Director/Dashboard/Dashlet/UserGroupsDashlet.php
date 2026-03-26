<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class UserGroupsDashlet extends Dashlet
{
    protected $icon = 'tags';

    public function getTitle()
    {
        return $this->translate('User Groups');
    }

    public function getSummary()
    {
        return $this->translate(
            'Defining Notifications for User Groups instead of single Users'
            . ' gives more flexibility'
        );
    }

    public function getUrl()
    {
        return 'director/usergroups';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
