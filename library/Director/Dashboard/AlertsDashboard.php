<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class AlertsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Notifications',
        'Users',
        'Timeperiods',
        'DependencyObject',
        'ScheduledDowntimeApply',
    );

    public function getTitle()
    {
        return $this->translate('Get alerts when something goes wrong');
    }
}
