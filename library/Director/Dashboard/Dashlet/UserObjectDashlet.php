<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class UserObjectDashlet extends Dashlet
{
    protected $icon = 'users';

    protected $requiredStats = ['user', 'usergroup'];

    public function getTitle()
    {
        return $this->translate('Users / Contacts');
    }

    public function getUrl()
    {
        return 'director/users';
    }
}
