<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Auth\Permission;

class TimeperiodObjectDashlet extends Dashlet
{
    protected $icon = 'calendar';

    protected $requiredStats = ['timeperiod'];

    public function getTitle()
    {
        return $this->translate('Timeperiods');
    }

    public function getUrl()
    {
        return 'director/timeperiods';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
