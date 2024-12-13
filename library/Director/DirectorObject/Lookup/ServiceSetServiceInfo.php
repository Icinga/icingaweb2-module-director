<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * A service belonging to a Service Set, attached either directly to the given
 * Host or to one of it's inherited Host Templates
 */
class ServiceSetServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $serviceName;

    /** @var string */
    protected $serviceSetName;

    /** @var UuidInterface */
    protected $uuid;

    public function __construct($hostName, $serviceName, $serviceSetName, UuidInterface $uuid)
    {
        $this->hostName = $hostName;
        $this->serviceName = $serviceName;
        $this->serviceSetName = $serviceSetName;
        $this->uuid = $uuid;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        $ids = [$host->get('id')];

        foreach (IcingaTemplateRepository::instanceByObject($host)->getTemplatesFor($host, true) as $parent) {
            $ids[] = $parent->get('id');
        }

        $db = $host->getConnection()->getDbAdapter();
        $query = $db->select()
            ->from(
                ['s' => 'icinga_service'],
                [
                    'service_set_name' => 'ss.object_name',
                    'uuid'             => 's.uuid',
                ]
            )->join(
                ['ss' => 'icinga_service_set'],
                's.service_set_id = ss.id',
                []
            )->join(
                ['hsi' => 'icinga_service_set_inheritance'],
                'hsi.parent_service_set_id = ss.id',
                []
            )->join(
                ['hs' => 'icinga_service_set'],
                'hs.id = hsi.service_set_id',
                []
            )->where('hs.host_id IN (?)', $ids)
            ->where('s.object_name = ?', $serviceName)
            ->where( // Ignore deactivated Services:
                'NOT EXISTS (SELECT 1 FROM icinga_host_service_blacklist hsb'
                . ' WHERE hsb.host_id = ? AND hsb.service_id = s.id)',
                (int) $host->get('id')
            );

        if ($row = $db->fetchRow($query)) {
            return new static(
                $host->getObjectName(),
                $serviceName,
                $row->service_set_name,
                Uuid::fromBytes(DbUtil::binaryResult($row->uuid))
            );
        }

        return null;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    public function getName()
    {
        return $this->serviceName;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getServiceSetName()
    {
        return $this->serviceSetName;
    }

    public function getUrl()
    {
        return Url::fromPath('director/host/servicesetservice', [
            'name'    => $this->hostName,
            'service' => $this->serviceName,
            'set'     => $this->serviceSetName,
        ]);
    }

    public function requiresOverrides()
    {
        return true;
    }
}
