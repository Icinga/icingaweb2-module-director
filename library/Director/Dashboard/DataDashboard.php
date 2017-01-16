<?php

namespace Icinga\Module\Director\Dashboard;

class DataDashboard extends Dashboard
{
    protected $dashletNames = array(
        'ImportSource',
        'Sync',
        'Datafield',
        'Datalist',
    );

    public function getTitle()
    {
        return $this->translate('Do more with your data');
    }
}
