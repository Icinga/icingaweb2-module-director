<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Ramsey\Uuid\UuidInterface;

/**
 * A single service, directly attached to a Host Object. Overrides might
 * still be used when use_var_overrides is true.
 */
class SingleServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $serviceName;

    /** @var bool */
    protected $useOverrides;

    /** @var UuidInterface */
    protected $uuid;

    public function __construct($hostName, $serviceName, UuidInterface $uuid, $useOverrides)
    {
        $this->hostName = $hostName;
        $this->serviceName = $serviceName;
        $this->useOverrides = $useOverrides;
        $this->uuid = $uuid;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        $keyParams = [
            'host_id' => $host->get('id'),
            'object_name' => $serviceName
        ];
        $connection = $host->getConnection();
        if (IcingaService::exists($keyParams, $connection)) {
            $service = IcingaService::load($keyParams, $connection);
            $useOverrides = $service->getResolvedVar('use_var_overrides') === 'y';

            return new static($host->getObjectName(), $serviceName, $service->getUniqueId(), $useOverrides);
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

    /**
     * @return UuidInterface
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    public function getUrl()
    {
        return Url::fromPath('director/service/edit', [
            'host' => $this->hostName,
            'name' => $this->serviceName,
        ]);
    }

    public function requiresOverrides()
    {
        return $this->useOverrides;
    }
}
