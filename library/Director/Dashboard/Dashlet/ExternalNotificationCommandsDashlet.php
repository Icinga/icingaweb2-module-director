<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ExternalNotificationCommandsDashlet extends CheckCommandsDashlet
{
    protected $icon = 'wrench';

    public function getSummary()
    {
        return $this->translate(
            'External Notification Commands have been defined in your local Icinga 2'
            . ' Configuration.'
        );
    }

    public function getTitle()
    {
        return $this->translate('External Notification Commands');
    }
}
