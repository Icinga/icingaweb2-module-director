<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class DatafieldDashlet extends Dashlet
{
    protected $icon = 'edit';

    public function getTitle()
    {
        return $this->translate('Define Data Fields');
    }

    public function getSummary()
    {
        return $this->translate(
            'Data fields make sure that configuration fits your rules'
        );
    }

    public function getUrl()
    {
        return 'director/data/fields';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
