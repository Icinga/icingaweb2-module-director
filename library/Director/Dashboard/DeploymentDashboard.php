<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class DeploymentDashboard extends Dashboard
{
    protected $dashletNames = array(
        'ActivityLog',
        'Deployment',
        'Infrastructure',
    );

    public function getTitle()
    {
        return $this->translate('Deploy configuration to your Icinga nodes');
    }
}
