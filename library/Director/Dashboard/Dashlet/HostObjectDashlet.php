<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class HostObjectDashlet extends Dashlet
{
    protected $icon = 'host';

    protected $requiredStats = ['host', 'hostgroup'];

    public function getTitle()
    {
        return $this->translate('Host objects');
    }

    public function listRequiredPermissions()
    {
        return [Permission::HOSTS];
    }

    public function getUrl()
    {
        return 'director/dashboard?name=hosts';
    }
}
