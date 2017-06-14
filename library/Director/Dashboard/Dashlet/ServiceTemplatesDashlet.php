<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ServiceTemplatesDashlet extends Dashlet
{
    protected $icon = 'services';

    public function getTitle()
    {
        return $this->translate('Service Templates');
    }

    public function getSummary()
    {
        return $this->translate(
            'Manage your Service Templates. Use Fields to make it easy for'
            . ' your users to get them customized.'
        );
    }

    public function getUrl()
    {
        return 'director/servicetemplates';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
