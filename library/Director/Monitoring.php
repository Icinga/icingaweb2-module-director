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
            $this->backend = MonitoringBackend::instance();
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

    public function getHostState($hostname)
    {
        $hostStates = array(
            '0'  => 'up',
            '1'  => 'down',
            '2'  => 'unreachable',
            '99' => 'pending',
        );

        $query = $this->backend->select()->from('hostStatus', array(
            'hostname'     => 'host_name',
            'state'        => 'host_state',
            'problem'      => 'host_problem',
            'acknowledged' => 'host_acknowledged',
            'in_downtime'  => 'host_in_downtime',
            'output'       => 'host_output',
        ))->where('host_name', $hostname);

        $res = $query->fetchRow();
        if ($res === false) {
            $res = (object) array(
                'hostname'     => $hostname,
                'state'        => '99',
                'problem'      => '0',
                'acknowledged' => '0',
                'in_downtime'  => '0',
                'output'       => null,
            );
        }

        $res->state = $hostStates[$res->state];

        return $res;
    }
}
