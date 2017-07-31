<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class UserGroupsDashlet extends Dashlet
{
    protected $icon = 'tags';

    public function getTitle()
    {
        return $this->translate('User Groups');
    }

    public function getSummary()
    {
        return $this->translate(
            'Defining Notifications for User Groups instead of single Users'
            . ' gives more flexibility'
        );
    }

    public function getUrl()
    {
        return 'director/usergroups';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
