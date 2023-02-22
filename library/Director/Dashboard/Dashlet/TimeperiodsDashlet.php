<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class TimeperiodsDashlet extends Dashlet
{
    protected $icon = 'calendar';

    protected $requiredStats = ['timeperiod'];

    public function getTitle()
    {
        return $this->translate('Timeperiods');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=timeperiods';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
