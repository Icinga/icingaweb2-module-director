<?php

namespace Icinga\Module\Director\Dashboard;

class CommandsDashboard extends Dashboard
{
    protected $dashletNames = array(
        'CheckCommands',
        'ExternalCheckCommands',
        'NotificationCommands',
        'ExternalNotificationCommands',
        'CommandTemplates',
    );

    public function getTitle()
    {
        return $this->translate('Manage your Icinga Commands');
    }

    public function getDescription()
    {
        return $this->translate(
            'Define Check-, Notification- or Event-Commands. Command definitions'
            . ' are the glue between your Host- and Service-Checks and the Check'
            . ' plugins on your Monitoring (or monitored) systems'
        );
    }
}
