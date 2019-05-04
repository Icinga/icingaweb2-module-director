<?php

namespace Icinga\Module\Director\Dashboard;

class TimeperiodsDashboard extends Dashboard
{
    protected $dashletNames = [
        'TimeperiodObject',
        'TimeperiodTemplate',
    ];

    public function getTitle()
    {
        return $this->translate('Define custom Time Periods');
    }

    public function getDescription()
    {
        return $this->translate(
            'Want to define to execute specific checks only withing specific'
            . ' time periods? Get mobile notifications only out of office hours,'
            . ' but mail notifications all around the clock? Time Periods allow'
            . ' you to tackle those and similar requirements.'
        );
    }

    public function getTabs()
    {
        return $this->createTabsForDashboards(
            ['notifications', 'users', 'timeperiods']
        );
    }
}
