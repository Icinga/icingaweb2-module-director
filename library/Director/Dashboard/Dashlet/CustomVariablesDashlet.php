<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class CustomVariablesDashlet extends Dashlet
{
    protected $icon = 'edit';

    public function getTitle()
    {
        return $this->translate('Manage Custom Variables');
    }

    public function getSummary()
    {
        return $this->translate(
            'A new custom variable support to manage custom variables required for your configuration. '
            . 'Make sure they fits your rules'
        );
    }

    public function getUrl()
    {
        return 'director/variables';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
