<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ZoneObjectDashlet extends Dashlet
{
    protected $icon = 'globe';

    protected $requiredStats = array('zone');

    public function getTitle()
    {
        return $this->translate('Zones');
    }

    public function getUrl()
    {
        return 'director/zones';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
