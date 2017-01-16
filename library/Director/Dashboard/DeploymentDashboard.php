<?php

namespace Icinga\Module\Director\Dashboard;

class DeploymentDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Deployment',
        'ActivityLog',
        'Job',
        'Infrastructure',
    );

    public function getTitle()
    {
        return $this->translate('Deploy configuration to your Icinga nodes');
    }
}
