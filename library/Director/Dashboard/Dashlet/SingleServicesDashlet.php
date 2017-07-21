<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class SingleServicesDashlet extends Dashlet
{
    protected $icon = 'service';

    public function getTitle()
    {
        return $this->translate('Single Services');
    }

    public function getSummary()
    {
        return $this->translate(
            'Here you can find all single services directly attached to single'
            . ' hosts'
        );
    }

    public function getUrl()
    {
        return 'director/services';
    }

    public function listRequiredPermissions()
    {
        return array('director/services');
    }
}
