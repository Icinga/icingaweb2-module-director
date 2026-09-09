<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class DataDashboard extends Dashboard
{
    protected $dashletNames = [
        'CustomVariables',
        'DatafieldCategory',
        'Datalist',
        'Datafield',
        'Customvar'
    ];

    public function getTitle()
    {
        return $this->translate('Do more with custom data');
    }
}
