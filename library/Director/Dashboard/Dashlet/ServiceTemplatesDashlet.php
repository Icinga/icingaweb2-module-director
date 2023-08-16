<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ServiceTemplatesDashlet extends Dashlet
{
    protected $icon = 'cubes';

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
        return 'director/services/templates';
    }

    public function listRequiredPermissions()
    {
        return [Permission::SERVICE_TEMPLATES];
    }
}
