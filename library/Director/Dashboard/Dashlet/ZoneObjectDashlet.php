<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ZoneObjectDashlet extends Dashlet
{
    protected $icon = 'globe';

    protected $requiredStats = ['zone'];

    public function getTitle()
    {
        return $this->translate('Zones');
    }

    public function getUrl()
    {
        return 'director/zones';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
