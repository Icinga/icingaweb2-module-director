<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;

class TimeperiodObjectDashlet extends Dashlet
{
    protected $icon = 'calendar';

    protected $requiredStats = array('timeperiod');

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
        return array('director/admin');
    }
}
