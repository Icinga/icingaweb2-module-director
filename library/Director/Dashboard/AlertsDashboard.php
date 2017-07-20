<?php

namespace Icinga\Module\Director\Dashboard;

class AlertsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Notifications',
        'Users',
        'Timeperiods',
    );

    public function getTitle()
    {
        return $this->translate('Get alerts when something goes wrong');
    }
}
