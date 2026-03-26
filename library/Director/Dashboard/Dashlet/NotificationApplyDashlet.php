<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class NotificationApplyDashlet extends Dashlet
{
    protected $icon = 'bell';

    protected $requiredStats = array('notification');

    public function getTitle()
    {
        return $this->translate('Notifications');
    }

    public function getSummary()
    {
        return $this->translate(
            'Apply notifications with specific properties according to given rules.'
        )  . ' ' . $this->getApplySummaryText('notification');
    }

    public function shouldBeShown()
    {
        return $this->getStats('notification', 'template') > 0;
    }

    public function listRequiredPermissions()
    {
        return [Permission::NOTIFICATIONS];
    }

    public function getUrl()
    {
        return 'director/notifications/applyrules';
    }
}
