<?php

namespace Icinga\Module\Director\Dashboard;

class DirectorDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Settings',
        'Basket',
        'SelfService',
    );

    public function getTitle()
    {
        return $this->translate('Icinga Director Configuration');
    }
}
