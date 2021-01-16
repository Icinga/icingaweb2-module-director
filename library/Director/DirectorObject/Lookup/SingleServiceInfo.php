<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;

class SingleServiceInfo implements ServiceInfo
{
    protected $hostName;

    protected $serviceName;

    public function __construct($hostName, $serviceName)
    {
        $this->hostName = $hostName;
        $this->serviceName= $serviceName;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        if (IcingaService::exists([
            'host_id' => $host->get('id'),
            'object_name' => $serviceName
        ], $host->getConnection())) {
            return new static($host->getObjectName(), $serviceName);
        }

        return false;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function getName()
    {
        return $this->serviceName;
    }

    public function getUrl()
    {
        return Url::fromPath('director/host/service', [
            'name'    => $this->hostName,
            'service' => $this->serviceName,
        ]);
    }
}
