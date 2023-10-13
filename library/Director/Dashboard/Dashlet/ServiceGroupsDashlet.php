<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ServiceGroupsDashlet extends Dashlet
{
    protected $icon = 'tags';

    public function getTitle()
    {
        return $this->translate('Service Groups');
    }

    public function getSummary()
    {
        return $this->translate(
            'Defining Service Groups get more structure. Great for Dashboards.'
            . ' Notifications and Permissions might be based on groups.'
        );
    }

    public function getUrl()
    {
        return 'director/servicegroups';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
