<?php

namespace Icinga\Module\Director\Dashboard;

class DataDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Datafield',
        'Datalist',
        // 'CustomVars'
    );

    public function getTitle()
    {
        return $this->translate('Do more with custom data');
    }
}
