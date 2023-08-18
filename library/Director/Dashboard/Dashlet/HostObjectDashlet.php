<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Auth\Permission;
use RuntimeException;

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
        throw new RuntimeException('This method should not be accessed, isAllowed() has been implemented');
    }

    public function isAllowed()
    {
        $acl = Acl::instance();
        return $acl->hasPermission(Permission::HOSTS)
            || $acl->hasPermission(Permission::HOST_TEMPLATES);
    }

    public function getUrl()
    {
        return 'director/dashboard?name=hosts';
    }
}
