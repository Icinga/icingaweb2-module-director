<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Auth\Permission;
use RuntimeException;

class CommandObjectDashlet extends Dashlet
{
    protected $icon = 'wrench';

    protected $requiredStats = ['command'];

    public function getTitle()
    {
        return $this->translate('Commands');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=commands';
    }

    public function listRequiredPermissions()
    {
        throw new RuntimeException('This method should not be accessed, isAllowed() has been implemented');
    }

    public function isAllowed()
    {
        $acl = Acl::instance();
        return $acl->hasPermission(Permission::COMMANDS)
            || $acl->hasPermission(Permission::COMMAND_TEMPLATES);
    }
}
