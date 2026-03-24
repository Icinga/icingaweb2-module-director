<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class ObjectsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'HostObject',
        'ServiceObject',
        'CommandObject',
    );

    public function getTitle()
    {
        return $this->translate('Define whatever you want to be monitored');
    }
}
