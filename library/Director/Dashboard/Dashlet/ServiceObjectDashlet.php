<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Acl;

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
        return ['director/services'];
    }

    public function isAllowed()
    {
        $acl = Acl::instance();
        return $acl->hasPermission('director/services')
            || $acl->hasPermission('director/service_sets');
    }
}
