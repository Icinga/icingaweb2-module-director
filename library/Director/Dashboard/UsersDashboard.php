<?php

namespace Icinga\Module\Director\Dashboard;

class UsersDashboard extends Dashboard
{
    protected $dashletNames = [
        'UserObject',
        'UserTemplate',
        'UserGroups',
    ];

    public function getTitle()
    {
        return $this->translate('Schedule your notifications');
    }

    public function getDescription()
    {
        return $this->translate(
            'This is where you manage your Icinga 2 User (Contact) objects. Try'
            . ' to keep your User objects simply by moving complexity to your'
            . ' templates. Bundle your users in groups and build Notifications'
            . ' based on them. Running MS Active Directory or another central'
            . ' User inventory? Stay away from fiddling with manual config, try'
            . ' to automate all the things with Imports and related Sync Rules!'
        );
    }

    public function getTabs()
    {
        return $this->createTabsForDashboards(
            ['notifications', 'users', 'timeperiods']
        );
    }
}
