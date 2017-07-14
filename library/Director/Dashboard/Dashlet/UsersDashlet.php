<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class UsersDashlet extends Dashlet
{
    protected $icon = 'users';

    protected $requiredStats = array('user', 'usergroup');

    public function getTitle()
    {
        return $this->translate('Users / Contacts');
    }

    public function listRequiredPermissions()
    {
        return array('director/users');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=users';
    }
}
