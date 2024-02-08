<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class InfrastructureDashlet extends Dashlet
{
    protected $icon = 'cloud';

    public function getTitle()
    {
        return $this->translate('Icinga Infrastructure');
    }

    public function getSummary()
    {
        return $this->translate(
            'Manage your Icinga 2 infrastructure: Masters, Zones, Satellites and more'
        );
    }

    public function getUrl()
    {
        return 'director/dashboard?name=infrastructure';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
