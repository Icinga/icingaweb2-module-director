<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class NotificationObjectDashlet extends Dashlet
{
    protected $icon = 'megaphone';

    protected $requiredStats = array('notification');

    public function getTitle()
    {
        return $this->translate('Notifications.');
    }

    public function getSummary()
    {
        return $this->translate('Schedule your notifications.')
            . ' ' . parent::getSummary();
    }

    public function getUrl()
    {
        return 'director/notifications';
    }
}
