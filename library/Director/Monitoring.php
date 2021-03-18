<?php

namespace Icinga\Module\Director;

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
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
        return $this->backend->select()->from('hostStatus', [
            'hostname' => 'host_name',
        ])->where('host_name', $hostname)->fetchOne() === $hostname;
    }

    public function hasService($hostname, $service)
    {
        return (array) $this->prepareServiceKeyColumnQuery($hostname, $service)->fetchRow() === [
            'hostname' => $hostname,
            'service'  => $service,
        ];
    }

    public function authCanEditHost(Auth $auth, $hostname)
    {
        if ($auth->hasPermission('director/monitoring/hosts')) {
            $restriction = null;
            foreach ($auth->getRestrictions('director/monitoring/rw-object-filter') as $restriction) {
                if ($this->hasHostWithExtraFilter($hostname, Filter::fromQueryString($restriction))) {
                    return true;
                }
            }
            if ($restriction === null) {
                return $this->hasHost($hostname);
            }
        }

        return false;
    }

    public function authCanEditService(Auth $auth, $hostname, $service)
    {
        if ($auth->hasPermission('director/monitoring/services')) {
            $restriction = null;
            foreach ($auth->getRestrictions('director/monitoring/rw-object-filter') as $restriction) {
                if ($this->hasServiceWithExtraFilter($hostname, $service, Filter::fromQueryString($restriction))) {
                    return true;
                }
            }
            if ($restriction === null) {
                return $this->hasService($hostname, $service);
            }
        }

        return false;
    }

    public function hasHostWithExtraFilter($hostname, Filter $filter)
    {
        return $this->backend->select()->from('hostStatus', [
            'hostname' => 'host_name',
            ])->where('host_name', $hostname)->applyFilter($filter)->fetchOne() === $hostname;
    }

    public function hasServiceWithExtraFilter($hostname, $service, Filter $filter)
    {
        return (array) $this
            ->prepareServiceKeyColumnQuery($hostname, $service)
            ->applyFilter($filter)
            ->fetchRow() === [
                'hostname' => $hostname,
                'service'  => $service,
            ];
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
