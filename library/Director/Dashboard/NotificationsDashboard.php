<?php

namespace Icinga\Module\Director\Dashboard;

class NotificationsDashboard extends Dashboard
{
    protected $dashletNames = [
        'NotificationApply',
        'NotificationTemplate',
    ];

    public function getTitle()
    {
        return $this->translate('Schedule your notifications');
    }

    public function getDescription()
    {
        return $this->translate(
            'Notifications are sent when a host or service reaches a non-ok hard'
            . ' state or recovers from such. One might also want to send them for'
            . ' special events like when a Downtime starts, a problem gets'
            . ' acknowledged and much more. You can send specific notifications'
            . ' only within specific time periods, you can delay them and of course'
            . ' re-notify at specific intervals.'
            . "\n\n"
            . ' Combine those possibilities in case you need to define escalation'
            . ' levels, like notifying operators first and your management later on'
            . ' in case the problem remains unhandled for a certain time.'
            . "\n\n"
            . '  You might send E-Mail or SMS, make phone calls or page on various'
            . ' channels. You could also delegate notifications to external service'
            . ' providers. The possibilities are endless, as you are allowed to'
            . ' define as many custom notification commands as you want'
        );
    }

    public function getTabs()
    {
        return $this->createTabsForDashboards(
            ['notifications', 'users', 'timeperiods']
        );
    }
}
