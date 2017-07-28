<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class NotificationTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = array('notification');

    public function getTitle()
    {
        return $this->translate('Notification templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your notifications.')
            . ' ' . $this->getTemplateSummaryText('notification');
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }

    public function getUrl()
    {
        return 'director/notifications/templates';
    }
}
