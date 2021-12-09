<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;

/**
 * A Service that makes part of a Service Set Apply Rule matching this Host,
 * generating a Service with the given name
 */
class AppliedServiceSetServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $serviceName;

    /** @var string */
    protected $serviceSetName;

    public function __construct($hostName, $serviceName, $serviceSetName)
    {
        $this->hostName = $hostName;
        $this->serviceName = $serviceName;
        $this->serviceSetName = $serviceSetName;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        $matcher = HostApplyMatches::prepare($host);
        $connection = $host->getConnection();
        foreach (static::fetchServiceSetApplyRulesByServiceName($connection, $host->get('id'), $serviceName) as $rule) {
            if ($matcher->matchesFilter($rule->filter)) {
                return new static(
                    $host->getObjectName(),
                    $serviceName,
                    $rule->service_set_name
                );
            }
        }

        return null;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * @return string
     */
    public function getServiceSetName()
    {
        return $this->serviceSetName;
    }

    public function getName()
    {
        return $this->serviceName;
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

    protected static function fetchServiceSetApplyRulesByServiceName(Db $connection, $hostId, $serviceName)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()
            ->from(['s' => 'icinga_service'], [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 'ss.assign_filter',
                'service_set_name' => 'ss.object_name',
            ])
            ->join(
                ['ss' => 'icinga_service_set'],
                's.service_set_id = ss.id',
                []
            )
            ->where('s.object_name = ?', $serviceName)
            ->where('ss.assign_filter IS NOT NULL')
            ->where( // Ignore deactivated Services:
                'NOT EXISTS (SELECT 1 FROM icinga_host_service_blacklist hsb'
                . ' WHERE hsb.host_id = ? AND hsb.service_id = s.id)',
                (int) $hostId
            );
        ;

        $allRules = $db->fetchAll($query);
        foreach ($allRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allRules;
    }
}
