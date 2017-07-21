<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ServiceSetsDashlet extends Dashlet
{
    protected $icon = 'pin';

    public function getTitle()
    {
        return $this->translate('Service Sets');
    }

    public function getSummary()
    {
        return $this->translate(
            'Grouping your Services into Sets allow you to quickly assign services'
            . ' often used together in a single operation all at once'
        );
    }

    public function getUrl()
    {
        return 'director/services/sets';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
