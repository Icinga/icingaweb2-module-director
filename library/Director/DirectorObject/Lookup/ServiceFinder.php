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

    public function __construct(IcingaHost $host, ?Auth $auth = null)
    {
        $this->host = $host;
        $this->auth = $auth;
        $this->db = $host->getConnection();
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        foreach (
            [
            SingleServiceInfo::class,
            InheritedServiceInfo::class,
            ServiceSetServiceInfo::class,
            AppliedServiceInfo::class,
            AppliedServiceSetServiceInfo::class,
            ] as $class
        ) {
            /** @var ServiceInfo $class */
            if ($info = $class::find($host, $serviceName)) {
                return $info;
            }
        }

        return false;
    }
}
