<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ServiceObjectDashlet extends Dashlet
{
    protected $icon = 'services';

    protected $requiredStats = array('service', 'servicegroup');

    public function getTitle()
    {
        return $this->translate('Monitored Services');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=services';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
