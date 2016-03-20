<?php

namespace Icinga\Module\Director;

use Icinga\Application\Icinga;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class Monitoring
{
    protected $backend;

    public function __construct()
    {
        $app = Icinga::app();
        $modules = $app->getModuleManager();
        if (!$modules->hasLoaded('monitoring') && $app->isCli()) {
            $app->getModuleManager()->loadEnabledModules();
        }

        if ($modules->hasLoaded('monitoring')) {
            $this->backend = MonitoringBackend::createBackend();
        }
    }

    public function isAvailable()
    {
        return $this->backend !== null;
    }

    public function hasHost($hostname)
    {
        return $this->backend->select()->from('hostStatus', array(
            'hostname' => 'host_name',
        ))->where('host_name', $hostname)->fetchOne() === $hostname;
    }
}
