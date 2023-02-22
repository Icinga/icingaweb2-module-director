<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ServiceApplyRulesDashlet extends Dashlet
{
    protected $icon = 'resize-full-alt';

    public function getTitle()
    {
        return $this->translate('Service Apply Rules');
    }

    public function getSummary()
    {
        return $this->translate(
            'Using Apply Rules a Service can be applied to multiple hosts at once,'
            . ' based on filters dealing with any combination of their properties'
        );
    }

    public function getUrl()
    {
        return 'director/services/applyrules';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
