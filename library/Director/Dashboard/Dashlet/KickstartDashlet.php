<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class KickstartDashlet extends Dashlet
{
    protected $icon = 'gauge';

    public function getTitle()
    {
        return $this->translate('Kickstart Helper');
    }

    public function getEscapedSummary()
    {
        return $this->translate(
            'This syncronizes Icinga Director to your Icinga 2 infrastructure'
        );
    }

    public function getUrl()
    {
        return 'director/kickstart';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
