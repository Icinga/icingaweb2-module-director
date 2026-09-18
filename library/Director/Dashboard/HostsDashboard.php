<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard;

class HostsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Hosts',
        'HostTemplates',
        'HostGroups',
        'HostChoices',
    );

    public function getTitle()
    {
        return $this->translate('Manage your Icinga Hosts');
    }

    public function getDescription()
    {
        return $this->translate(
            'This is where you manage your Icinga 2 Host Checks. Host templates'
            . ' are your main building blocks. You can bundle them to "choices",'
            . ' allowing (or forcing) your users to choose among a given set of'
            . ' preconfigured templates.'
        );
    }

    public function getTabs()
    {
        return $this->createTabsForDashboards(
            ['hosts', 'services', 'commands']
        );
    }
}
