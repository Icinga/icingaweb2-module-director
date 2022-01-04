<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class NotificationsDashlet extends Dashlet
{
    protected $icon = 'bell';

    protected $requiredStats = array('notification');

    public function getTitle()
    {
        return $this->translate('Notifications');
    }

    public function getSummary()
    {
        return $this->translate(
            'Schedule your notifications. Define who should be notified, when,'
            . ' and for which kind of problem'
        );
    }

    public function listRequiredPermissions()
    {
        return array('director/notifications');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=notifications';
    }
}
