<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class NotificationApplyDashlet extends Dashlet
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
            'Apply notifications with specific properties according to given rules.'
        )  . ' ' . $this->getApplySummaryText('notification');
    }

    public function shouldBeShown()
    {
        return $this->getStats('notification', 'template') > 0;
    }

    public function listRequiredPermissions()
    {
        return array('director/notifications');
    }

    public function getUrl()
    {
        return 'director/notifications/applyrules';
    }
}
