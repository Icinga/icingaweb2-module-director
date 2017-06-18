<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class CommandObjectDashlet extends Dashlet
{
    protected $icon = 'wrench';

    protected $requiredStats = array('command');

    public function getTitle()
    {
        return $this->translate('Commands');
    }

    public function getUrl()
    {
        return 'director/dashboard?name=commands';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
