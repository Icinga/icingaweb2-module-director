<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class CheckCommandsDashlet extends Dashlet
{
    protected $icon = 'wrench';

    public function getSummary()
    {
        return $this->translate(
            'Manage definitions for your Commands that should be executed as'
            . ' Check Plugins, Notifications or based on Events'
        );
    }

    public function getTitle()
    {
        return $this->translate('Commands');
    }

    public function listRequiredPermissions()
    {
        return [Permission::COMMANDS];
    }

    public function getUrl()
    {
        return 'director/commands';
    }
}
