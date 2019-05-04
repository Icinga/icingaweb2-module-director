<?php

namespace Icinga\Module\Director\Dashboard;

class DeploymentDashboard extends Dashboard
{
    protected $dashletNames = array(
        'ActivityLog',
        'Deployment',
        'Infrastructure',
    );

    public function getTitle()
    {
        return $this->translate('Deploy configuration to your Icinga nodes');
    }
}
