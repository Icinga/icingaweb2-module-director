<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class HostTemplatesDashlet extends Dashlet
{
    protected $icon = 'cubes';

    public function getTitle()
    {
        return $this->translate('Host Templates');
    }

    public function getSummary()
    {
        return $this->translate(
            'Manage your Host Templates. Use Fields to make it easy for'
            . ' your users to get them customized.'
        );
    }

    public function getUrl()
    {
        return 'director/hosts/templates';
    }

    public function listRequiredPermissions()
    {
        return [Permission::HOST_TEMPLATES];
    }
}
