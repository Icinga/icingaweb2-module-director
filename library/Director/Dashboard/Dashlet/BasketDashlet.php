<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class BasketDashlet extends Dashlet
{
    protected $icon = 'tag';

    public function getTitle()
    {
        return $this->translate('Object Basket');
    }

    public function getSummary()
    {
        return $this->translate(
            'Preserve specific objects in a specific state'
        );
    }

    public function getUrl()
    {
        return 'director/baskets';
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }
}
