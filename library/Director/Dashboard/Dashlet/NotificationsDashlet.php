<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class NotificationsDashlet extends Dashlet
{
    protected $icon = 'bell';

    protected $requiredStats = ['notification'];

    public function getTitle()
    {
        return $this->translate('Notifications');
    }

    public function getSummary()
    {
        return $this->translate(
            'Schedule your notifications. Define who should be notified, when,'
            . ' and for which kind of problem'
        );
    }

    public function listRequiredPermissions()
    {
        return [Permission::NOTIFICATIONS];
    }

    public function getUrl()
    {
        return 'director/dashboard?name=notifications';
    }
}
