<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;

class AppliedServiceInfo implements ServiceInfo
{
    /** @var string */
    protected $hostName;

    /** @var string */
    protected $serviceName;

    /** @var int */
    protected $serviceApplyRuleId;

    public function __construct($hostName, $serviceName, $serviceApplyRuleId)
    {
        $this->hostName = $hostName;
        $this->serviceName= $serviceName;
        $this->serviceApplyRuleId = $serviceApplyRuleId;
    }

    public static function find(IcingaHost $host, $serviceName)
    {
        $matcher = HostApplyMatches::prepare($host);
        $connection = $host->getConnection();
        foreach (static::fetchApplyRulesByServiceName($connection, $serviceName) as $rule) {
            if ($matcher->matchesFilter($rule->filter)) {
                return new static($host->getObjectName(), $serviceName, (int) $rule->id);
            }
        }

        return null;
    }

    public function getHostName()
    {
        return $this->hostName;
    }

    /**
     * @return int
     */
    public function getServiceApplyRuleId()
    {
        return $this->serviceApplyRuleId;
    }

    public function getName()
    {
        return $this->serviceName;
    }

    public function getUrl()
    {
        return Url::fromPath('director/host/appliedservice', [
            'name'       => $this->hostName,
            'service_id' => $this->serviceApplyRuleId,
        ]);
    }

    protected static function fetchApplyRulesByServiceName(Db $connection, $serviceName)
    {
        $db = $connection->getDbAdapter();
        $query = $db->select()
            ->from(['s' => 'icinga_service'], [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
            ])
            ->where('object_name = ?', $serviceName)
            ->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        $allRules = $db->fetchAll($query);
        foreach ($allRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allRules;
    }
}
