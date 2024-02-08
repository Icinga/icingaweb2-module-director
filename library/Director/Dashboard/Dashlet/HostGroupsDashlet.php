<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class HostGroupsDashlet extends Dashlet
{
    protected $icon = 'tags';

    public function getTitle()
    {
        return $this->translate('Host Groups');
    }

    public function getSummary()
    {
        return $this->translate(
            'Define Host Groups to give your configuration more structure. They'
            . ' are useful for Dashboards, Notifications or Restrictions'
        );
    }

    public function getUrl()
    {
        return 'director/hostgroups';
    }

    public function listRequiredPermissions()
    {
        return [Permission::HOST_GROUPS];
    }
}
