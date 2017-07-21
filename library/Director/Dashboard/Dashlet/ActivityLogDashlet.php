<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ActivityLogDashlet extends Dashlet
{
    protected $icon = 'book';

    public function getTitle()
    {
        return $this->translate('Activity Log');
    }

    public function getSummary()
    {
        return $this->translate(
            'Wondering about what changed why? Track your changes!'
        );
    }

    public function listCssClasses()
    {
        return 'state-ok';
    }

    public function getUrl()
    {
        return 'director/config/activities';
    }

    public function listRequiredPermissions()
    {
        return array('director/audit');
    }
}
