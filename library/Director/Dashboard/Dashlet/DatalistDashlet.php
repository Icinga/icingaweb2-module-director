<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class DatalistDashlet extends Dashlet
{
    protected $icon = 'sort-name-up';

    public function getTitle()
    {
        return $this->translate('Provide Data Lists');
    }

    public function getSummary()
    {
        return $this->translate(
            'Provide data lists to make life easier for your users'
        );
    }

    public function getUrl()
    {
        return 'director/data/lists';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
