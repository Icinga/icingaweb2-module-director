<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class HostsDashlet extends Dashlet
{
    protected $icon = 'host';

    public function getTitle()
    {
        return $this->translate('Hosts');
    }

    public function getSummary()
    {
        return $this->translate(
            'This is where you add all your servers, containers, network or'
            . ' sensor devices - and much more. Every subject worth to be'
            . ' monitored'
        );
    }

    public function getUrl()
    {
        return 'director/hosts';
    }

    public function listRequiredPermissions()
    {
        return [Permission::HOSTS];
    }
}
