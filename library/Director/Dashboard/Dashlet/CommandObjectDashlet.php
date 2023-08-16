<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

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
        return [Permission::COMMANDS];
    }
}
