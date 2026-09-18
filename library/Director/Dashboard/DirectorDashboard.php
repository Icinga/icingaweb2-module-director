<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class DirectorDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Settings',
        'Basket',
        'SelfService',
    );

    public function getTitle()
    {
        return $this->translate('Icinga Director Configuration');
    }
}
