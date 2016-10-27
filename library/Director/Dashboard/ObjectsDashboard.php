<?php

namespace Icinga\Module\Director\Dashboard;

use Countable;

use Icinga\Module\Director\Dashboard\Dashlet\Dashlet;

class ObjectsDashboard extends Dashboard
{
    protected $name;

    protected $dashletNames = array(
        'HostObject',
        'ServiceObject',
        'CommandObject',
        'UserObject',
        'NotificationObject',
        'TimeperiodObject',
    );

    public function getTitle()
    {
        return $this->translate('Define whatever you want to be monitored');
    }

    public function getDescription()
    {
        return $this->translate('...');
    }
}
