<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class NotificationTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = ['notification'];

    public function getTitle()
    {
        return $this->translate('Notification templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your notifications.')
            . ' ' . $this->getTemplateSummaryText('notification');
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }

    public function getUrl()
    {
        return 'director/notifications/templates';
    }
}
