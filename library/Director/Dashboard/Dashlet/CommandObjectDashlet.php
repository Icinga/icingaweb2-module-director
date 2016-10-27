<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class CommandObjectDashlet extends Dashlet
{
    protected $icon = 'wrench';

    protected $requiredStats = array('command');

    public function getTitle()
    {
        return $this->view->translate('Commands');
    }

    public function getUrl()
    {
        return 'director/commands';
    }
}
