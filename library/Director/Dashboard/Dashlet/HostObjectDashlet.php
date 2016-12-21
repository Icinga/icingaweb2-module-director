<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class HostObjectDashlet extends Dashlet
{
    protected $icon = 'host';

    protected $requiredStats = array('host', 'hostgroup');

    public function getTitle()
    {
        return $this->translate('Host objects');
    }

    public function getUrl()
    {
        return 'director/hosts';
    }
}
