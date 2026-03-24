<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class TimeperiodsDashlet extends Dashlet
{
    protected $icon = 'calendar';

    protected $requiredStats = ['timeperiod'];

    public function getTitle()
    {
        return $this->translate('Timeperiods');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=timeperiods';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
