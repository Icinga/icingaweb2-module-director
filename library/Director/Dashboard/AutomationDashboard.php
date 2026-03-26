<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class AutomationDashboard extends Dashboard
{
    protected $dashletNames = array(
        'ImportSource',
        'Sync',
        'Job'
    );

    public function getTitle()
    {
        return $this->translate('Automate all tasks');
    }
}
