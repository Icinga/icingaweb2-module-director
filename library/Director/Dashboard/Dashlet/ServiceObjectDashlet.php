<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Auth\Permission;
use RuntimeException;

class ServiceObjectDashlet extends Dashlet
{
    protected $icon = 'services';

    protected $requiredStats = array('service', 'servicegroup');

    public function getTitle()
    {
        return $this->translate('Monitored Services');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=services';
    }

    public function listRequiredPermissions()
    {
        throw new RuntimeException('This method should not be accessed, isAllowed() has been implemented');
    }

    public function isAllowed()
    {
        $acl = Acl::instance();
        return $acl->hasPermission(Permission::SERVICES)
            || $acl->hasPermission(Permission::SERVICE_SETS);
    }
}
