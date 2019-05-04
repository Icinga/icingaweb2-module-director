<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class TimeperiodsDashlet extends Dashlet
{
    protected $icon = 'calendar';

    protected $requiredStats = array('timeperiod');

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
        return array('director/admin');
    }
}
