<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;

class InheritedServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $hostTemplateName;

    /** @var string */
    protected $serviceName;

    public function __construct($hostName, $hostTemplateName, $serviceName)
    {
        $this->hostName = $hostName;
        $this->hostTemplateName = $hostTemplateName;
        $this->serviceName= $serviceName;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        foreach (IcingaTemplateRepository::instanceByObject($host)->getTemplatesFor($host, true) as $parent) {
            if (IcingaService::exists([
                'host_id'     => $parent->get('id'),
                'object_name' => $serviceName
            ], $host->getConnection())) {
                return new static(
                    $host->getObjectName(),
                    $parent->getObjectName(),
                    $serviceName
                );
            }
        }

        return false;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * @return string
     */
    public function getHostTemplateName()
    {
        return $this->hostTemplateName;
    }

    public function getName()
    {
        return $this->serviceName;
    }

    public function getUrl()
    {
        return Url::fromPath('director/host/inheritedservice', [
            'name'          => $this->hostName,
            'service'       => $this->serviceName,
            'inheritedFrom' => $this->hostTemplateName
        ]);
    }

    public function requiresOverrides()
    {
        return true;
    }
}
