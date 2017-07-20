<?php

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
