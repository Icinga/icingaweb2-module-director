<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Integration\MonitoringModule\Monitoring;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use RuntimeException;

class ServiceFinder
{
    /** @var IcingaHost */
    protected $host;

    /** @var ?Auth */
    protected $auth;

    /** @var IcingaHost[] */
    protected $parents;

    /** @var HostApplyMatches */
    protected $applyMatcher;

    /** @var \Icinga\Module\Director\Db */
    protected $db;

    public function __construct(IcingaHost $host, Auth $auth = null)
    {
        $this->host = $host;
        $this->auth = $auth;
        $this->db = $host->getConnection();
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        foreach ([
            SingleServiceInfo::class,
            InheritedServiceInfo::class,
            ServiceSetServiceInfo::class,
            AppliedServiceInfo::class,
            AppliedServiceSetServiceInfo::class,
        ] as $class) {
            /** @var ServiceInfo $class */
            if ($info = $class::find($host, $serviceName)) {
                return $info;
            }
        }

        return false;
    }

    /**
     * @param $serviceName
     * @return Url
     */
    public function getRedirectionUrl($serviceName)
    {
        if ($this->auth === null) {
            throw new RuntimeException('Auth is required for ServiceFinder when dealing when asking for URLs');
        }
        if ($this->auth->hasPermission(Permission::HOSTS)) {
            if ($info = $this::find($this->host, $serviceName)) {
                return $info->getUrl();
            }
        }
        if ($this->auth->hasPermission(Permission::MONITORING_HOSTS)) {
            if ($info = $this::find($this->host, $serviceName)) {
                if ((new Monitoring($this->auth))->canModifyService($this->host->getObjectName(), $serviceName)) {
                    return $info->getUrl();
                }
            }
        }
        if ($this->auth->hasPermission(Permission::MONITORING_SERVICES_RO)) {
            return Url::fromPath('director/host/servicesro', [
                'name'    => $this->host->getObjectName(),
                'service' => $serviceName
            ]);
        }

        return Url::fromPath('director/host/invalidservice', [
            'name'    => $this->host->getObjectName(),
            'service' => $serviceName,
        ]);
    }
}
