<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

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
        return array('director/scheduled-downtimes');
    }

    public function getUrl()
    {
        return 'director/scheduled-downtimes/applyrules';
    }
}
