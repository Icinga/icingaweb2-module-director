<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class SettingsDashlet extends Dashlet
{
    protected $icon = 'edit';

    public function getTitle()
    {
        return $this->translate('Director Settings');
    }

    public function getSummary()
    {
        return $this->translate(
            'Tweak some global Director settings'
        );
    }

    public function getUrl()
    {
        return 'director/config/settings';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
