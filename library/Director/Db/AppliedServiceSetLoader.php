<?php

namespace Icinga\Module\Director\Db;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;

class AppliedServiceSetLoader
{
    protected $host;

    public function __construct(IcingaHost $host)
    {
        $this->host = $host;
    }

    /**
     * @return IcingaServiceSet[]
     */
    public static function fetchForHost(IcingaHost $host)
    {
        $loader = new static($host);
        return $loader->fetchAppliedServiceSets();
    }

    /**
     * @return IcingaServiceSet[]
     */
    protected function fetchAppliedServiceSets()
    {
        $sets = array();
        $matcher = HostApplyMatches::prepare($this->host);
        foreach ($this->fetchAllServiceSets() as $set) {
            $filter = Filter::fromQueryString($set->get('assign_filter'));
            if ($matcher->matchesFilter($filter)) {
                $sets[] = $set;
            }
        }

        return $sets;
    }

    /**
     * @return IcingaServiceSet[]
     */
    protected function fetchAllServiceSets()
    {
        $db = $this->host->getDb();
        $query = $db
            ->select()
            ->from('icinga_service_set')
            ->where('assign_filter IS NOT NULL');

        return IcingaServiceSet::loadAll($this->host->getConnection(), $query);
    }
}
