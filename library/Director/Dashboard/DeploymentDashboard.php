<?php

namespace Icinga\Module\Director\Dashboard;

class DeploymentDashboard extends Dashboard
{
    protected $name;

    protected $dashletNames = array(
        'Deployment',
        'ActivityLog',
        'ApiUserObject',
        'EndpointObject',
        'ZoneObject',
    );

    public function getTitle()
    {
        return $this->translate('Deploy configuration to your Icinga nodes');
    }

    public function getDescription()
    {
        return $this->translate('...');
    }
}
