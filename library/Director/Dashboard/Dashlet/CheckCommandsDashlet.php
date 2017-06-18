<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class CheckCommandsDashlet extends Dashlet
{
    protected $icon = 'wrench';

    public function getSummary()
    {
        return $this->translate(
            'Check Commands are executed every time a Host or Service check has'
            . ' to be run'
        );
    }

    public function getTitle()
    {
        return $this->translate('Check Commands');
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }

    public function getUrl()
    {
        return 'director/commands';
    }
}
