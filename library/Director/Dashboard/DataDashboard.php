<?php

namespace Icinga\Module\Director\Dashboard;

class DataDashboard extends Dashboard
{
    protected $dashletNames = [
        'Datafield',
        'DatafieldCategory',
        'Datalist',
        'Customvar'
    ];

    public function getTitle()
    {
        return $this->translate('Do more with custom data');
    }
}
