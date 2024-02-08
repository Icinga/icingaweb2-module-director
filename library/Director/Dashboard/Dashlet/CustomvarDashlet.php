<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class CustomvarDashlet extends Dashlet
{
    protected $icon = 'keyboard';

    public function getTitle()
    {
        return $this->translate('CustomVar Overview');
    }

    public function getSummary()
    {
        return $this->translate(
            'Get an overview of used CustomVars and their variants'
        );
    }

    public function getUrl()
    {
        return 'director/data/vars';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
