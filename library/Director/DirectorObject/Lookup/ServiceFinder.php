<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;

class ServiceFinder
{
    /** @var IcingaHost */
    protected $host;

    /** @var Auth */
    protected $auth;

    /** @var IcingaHost[] */
    protected $parents;

    /** @var HostApplyMatches */
    protected $applyMatcher;

    /** @var \Icinga\Module\Director\Db */
    protected $db;

    public function __construct(IcingaHost $host, Auth $auth)
    {
        $this->host = $host;
        $this->auth = $auth;
        $this->db = $host->getConnection();
    }

    /**
     * @param $serviceName
     * @return Url
     */
    public function getRedirectionUrl($serviceName)
    {
        if ($this->auth->hasPermission('director/host')) {
            foreach ([
                SingleServiceInfo::class,
                InheritedServiceInfo::class,
                ServiceSetServiceInfo::class,
                AppliedServiceInfo::class,
                AppliedServiceSetServiceInfo::class,
            ] as $class) {
                /** @var ServiceInfo $class */
                if ($info = $class::find($this->host, $serviceName)) {
                    return $info->getUrl();
                }
            }
        }
        if ($this->auth->hasPermission('director/monitoring/services-ro')) {
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
