<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ApiUserObjectDashlet extends Dashlet
{
    protected $icon = 'lock-open-alt';

    protected $requiredStats = array('apiuser');

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
        return array('director/admin');
    }
}
