<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class SelfServiceDashlet extends Dashlet
{
    protected $icon = 'chat';

    public function getTitle()
    {
        return $this->translate('Self Service API');
    }

    public function getSummary()
    {
        return $this->translate(
            'Icinga Director offers a Self Service API, allowing new Icinga'
            . ' nodes to register themselves'
        );
    }

    public function getUrl()
    {
        return 'director/settings/self-service';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
