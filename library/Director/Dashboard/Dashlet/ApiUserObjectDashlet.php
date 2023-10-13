<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ApiUserObjectDashlet extends Dashlet
{
    protected $icon = 'lock-open-alt';

    protected $requiredStats = ['apiuser'];

    public function getTitle()
    {
        return $this->translate('Icinga Api users');
    }

    public function getUrl()
    {
        return 'director/apiusers';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
