<?php

namespace Icinga\Module\Director\Dashboard;

class ObjectsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'HostObject',
        'ServiceObject',
        'CommandObject',
        // 'Notifications',
        'DependencyObject',
    );

    public function getTitle()
    {
        return $this->translate('Define whatever you want to be monitored');
    }
}
