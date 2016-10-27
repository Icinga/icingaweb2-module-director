<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use DirectoryIterator;
use Icinga\Exception\ProgrammingError;

class ServiceObjectDashlet extends Dashlet
{
    protected $icon = 'services';

    protected $requiredStats = array('service', 'servicegroup');

    public function getTitle()
    {
        return $this->translate('Monitored Services');
    }

    public function getUrl()
    {
        return 'director/services';
    }
}
