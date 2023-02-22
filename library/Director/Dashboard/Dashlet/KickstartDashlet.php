<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class KickstartDashlet extends Dashlet
{
    protected $icon = 'gauge';

    public function getTitle()
    {
        return $this->translate('Kickstart Wizard');
    }

    public function getSummary()
    {
        return $this->translate(
            'This synchronizes Icinga Director to your Icinga 2 infrastructure.'
            . ' A new run should be triggered on infrastructure changes'
        );
    }

    public function getUrl()
    {
        return 'director/kickstart';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
