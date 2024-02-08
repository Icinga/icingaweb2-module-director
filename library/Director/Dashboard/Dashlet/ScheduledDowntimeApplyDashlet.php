<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ScheduledDowntimeApplyDashlet extends Dashlet
{
    protected $icon = 'plug';

    protected $requiredStats = ['scheduled_downtime'];

    public function getTitle()
    {
        return $this->translate('Scheduled Downtimes');
    }

    public function listRequiredPermissions()
    {
        return [Permission::SCHEDULED_DOWNTIMES];
    }

    public function getUrl()
    {
        return 'director/scheduled-downtimes/applyrules';
    }
}
