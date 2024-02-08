<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class UsersDashlet extends Dashlet
{
    protected $icon = 'users';

    protected $requiredStats = ['user', 'usergroup'];

    public function getTitle()
    {
        return $this->translate('Users / Contacts');
    }

    public function listRequiredPermissions()
    {
        return [Permission::USERS];
    }

    public function getUrl()
    {
        return 'director/dashboard?name=users';
    }
}
