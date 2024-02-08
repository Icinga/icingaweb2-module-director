<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class BasketDashlet extends Dashlet
{
    protected $icon = 'tag';

    public function getTitle()
    {
        return $this->translate('Configuration Baskets');
    }

    public function getSummary()
    {
        return $this->translate(
            'Preserve specific configuration objects in a specific state'
        );
    }

    public function getUrl()
    {
        return 'director/baskets';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
