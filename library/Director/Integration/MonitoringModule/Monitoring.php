<?php

namespace Icinga\Module\Director\Integration\MonitoringModule;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Auth\MonitoringRestriction;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Backend\MonitorBackend;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Url;

class Monitoring implements MonitorBackend
{
    /** @var ?MonitoringBackend */
    protected $backend;

    /** @var Auth */
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->initializeMonitoringBackend();
    }

    public function isAvailable(): bool
    {
        return $this->backend !== null;
    }

    public function getHostUrl(string $hostname): Url
    {
        return Url::fromPath('monitoring/host/show', ['host' => $hostname]);
    }

    public function hasHost($hostname): bool
    {
        return $this->hasHostByName($hostname);
    }

    public function hasHostByName($hostname): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            return $this->selectHost($hostname)->fetchOne() === $hostname;
        } catch (Exception $_) {
            return false;
        }
    }


    public function hasService($hostname, $service): bool
    {
        return $this->hasServiceByName($hostname, $service);
    }

    public function hasServiceByName($hostname, $service): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        try {
            return $this->rowIsService($this->selectService($hostname, $service)->fetchRow(), $hostname, $service);
        } catch (Exception $_) {
            return false;
        }
    }

    public function canModifyService(string $hostName, string $serviceName): bool
    {
        return $this->canModifyServiceByName($hostName, $serviceName);
    }

    public function canModifyServiceByName($hostname, $service): bool
    {
        if (! $this->isAvailable() || $hostname === null || $service === null) {
            return false;
        }
        if ($this->auth->hasPermission(Permission::MONITORING_SERVICES)) {
            $restriction = null;
            foreach ($this->auth->getRestrictions(Restriction::MONITORING_RW_OBJECT_FILTER) as $restriction) {
                if ($this->hasServiceWithFilter($hostname, $service, Filter::fromQueryString($restriction))) {
                    return true;
                }
            }
            if ($restriction === null) {
                return $this->hasServiceByName($hostname, $service);
            }
        }

        return false;
    }

    public function canModifyHost(string $hostName): bool
    {
        return $this->canModifyHostByName($hostName);
    }

    public function canModifyHostByName($hostname): bool
    {
        if ($this->isAvailable() && $this->auth->hasPermission(Permission::MONITORING_HOSTS)) {
            $restriction = null;
            foreach ($this->auth->getRestrictions(Restriction::MONITORING_RW_OBJECT_FILTER) as $restriction) {
                if ($this->hasHostWithFilter($hostname, Filter::fromQueryString($restriction))) {
                    return true;
                }
            }
            if ($restriction === null) {
                return $this->hasHostByName($hostname);
            }
        }

        return false;
    }

    protected function hasHostWithFilter($hostname, Filter $filter): bool
    {
        try {
            return $this->selectHost($hostname)->applyFilter($filter)->fetchOne() === $hostname;
        } catch (Exception $e) {
            return false;
        }
    }

    public function hasServiceWithFilter($hostname, $service, Filter $filter): bool
    {
        try {
            return $this->rowIsService(
                $this->selectService($hostname, $service)->applyFilter($filter)->fetchRow(),
                $hostname,
                $service
            );
        } catch (Exception $e) {
            return false;
        }
    }

    protected function selectHost($hostname)
    {
        return $this->selectHostStatus($hostname, [
            'hostname' => 'host_name',
        ]);
    }

    protected function selectHostStatus($hostname, $columns)
    {
        return $this->restrictQuery(
            $this->backend
                ->select()
                ->from('hostStatus', $columns)
                ->where('host_name', $hostname)
        );
    }

    protected function selectService($hostname, $service)
    {
        return $this->selectServiceStatus($hostname, $service, [
            'hostname' => 'host_name',
            'service'  => 'service_description',
        ]);
    }

    protected function selectServiceStatus($hostname, $service, $columns)
    {
        return $this->restrictQuery(
            $this->backend
                ->select()
                ->from('serviceStatus', $columns)
                ->where('host_name', $hostname)
                ->where('service_description', $service)
        );
    }

    protected function restrictQuery($query)
    {
        $query->applyFilter(MonitoringRestriction::getObjectsFilter($this->auth));
        return $query;
    }

    protected function rowIsService($row, $hostname, $service): bool
    {
        return (array) $row === [
            'hostname' => $hostname,
            'service'  => $service,
        ];
    }

    protected function initializeMonitoringBackend()
    {
        $app = Icinga::app();
        $modules = $app->getModuleManager();
        if (!$modules->hasLoaded('monitoring') && $app->isCli()) {
            $modules->loadEnabledModules();
        }

        if ($modules->hasLoaded('monitoring')) {
            try {
                $this->backend = MonitoringBackend::instance();
            } catch (ConfigurationError $e) {
                $this->backend = null;
            }
        }
    }
}
