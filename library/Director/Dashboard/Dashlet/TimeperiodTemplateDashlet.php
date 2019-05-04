<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class TimeperiodTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = array('timeperiod');

    public function getTitle()
    {
        return $this->translate('Timeperiod Templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your TimePeriod objects.')
            . ' ' . $this->getTemplateSummaryText('timeperiod');
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }

    public function getUrl()
    {
        return 'director/timeperiods/templates';
    }
}
