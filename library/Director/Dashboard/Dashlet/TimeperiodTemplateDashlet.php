<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class TimeperiodTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = ['timeperiod'];

    public function getTitle()
    {
        return $this->translate('Timeperiod Templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your TimePeriod objects.')
            . ' ' . $this->getTemplateSummaryText('timeperiod');
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }

    public function getUrl()
    {
        return 'director/timeperiods/templates';
    }
}
