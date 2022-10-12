<?php

namespace Icinga\Module\Director;

use gipfl\IcingaWeb2\Link;
use Icinga\Application\Icinga;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class MonitorBackendMonitoring implements MonitorBackend
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
        if ($this->backend === null) {
            return false;
        }

        return $this->backend->select()->from('hostStatus', [
            'hostname' => 'host_name',
        ])->where('host_name', $hostname)->fetchOne() === $hostname;
    }

    public function hasService($hostname, $service)
    {
        if ($this->backend === null) {
            return false;
        }

        return (array) $this->prepareServiceKeyColumnQuery($hostname, $service)->fetchRow() === [
            'hostname' => $hostname,
            'service'  => $service,
        ];
    }

    public function getHostLink($title, $hostname, array $attributes = null)
    {
        return Link::create(
            $title,
            'monitoring/host/show',
            ['host' => $hostname],
            $attributes
        );
    }

    public function getHostState($hostname)
    {
        $hostStates = [
            '0'  => 'up',
            '1'  => 'down',
            '2'  => 'unreachable',
            '99' => 'pending',
        ];

        $query = $this->backend->select()->from('hostStatus', [
            'hostname'     => 'host_name',
            'state'        => 'host_state',
            'problem'      => 'host_problem',
            'acknowledged' => 'host_acknowledged',
            'in_downtime'  => 'host_in_downtime',
            'output'       => 'host_output',
        ])->where('host_name', $hostname);

        $res = $query->fetchRow();
        if ($res === false) {
            $res = (object) [
                'hostname'     => $hostname,
                'state'        => '99',
                'problem'      => '0',
                'acknowledged' => '0',
                'in_downtime'  => '0',
                'output'       => null,
            ];
        }

        $res->state = $hostStates[$res->state];

        return $res;
    }

    protected function prepareServiceKeyColumnQuery($hostname, $service)
    {
        return $this->backend
            ->select()
            ->from('serviceStatus', [
                'hostname' => 'host_name',
                'service'  => 'service_description',
            ])
            ->where('host_name', $hostname)
            ->where('service_description', $service);
    }
}
