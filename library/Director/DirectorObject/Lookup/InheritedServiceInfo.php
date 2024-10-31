<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Ramsey\Uuid\UuidInterface;

/**
 * A Service attached to a parent Service Template. This is a shortcut for
 * 'assign where "Template Name" in templates'
 */
class InheritedServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $hostTemplateName;

    /** @var string */
    protected $serviceName;

    /** @var UuidInterface */
    protected $uuid;

    public function __construct($hostName, $hostTemplateName, $serviceName, UuidInterface $uuid)
    {
        $this->hostName = $hostName;
        $this->hostTemplateName = $hostTemplateName;
        $this->serviceName = $serviceName;
        $this->uuid = $uuid;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        $db = $host->getConnection();
        foreach (IcingaTemplateRepository::instanceByObject($host)->getTemplatesFor($host, true) as $parent) {
            $key = [
                'host_id'     => $parent->get('id'),
                'object_name' => $serviceName
            ];
            if (IcingaService::exists($key, $db)) {
                return new static(
                    $host->getObjectName(),
                    $parent->getObjectName(),
                    $serviceName,
                    IcingaService::load($key, $db)->getUniqueId()
                );
            }
        }

        return false;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function getUuid()
    {
        return $this->uuid;
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
