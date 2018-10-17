<?php

namespace Icinga\Module\Director\Db;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;

class ServiceSetHostLoader
{
    protected $set;

    public function __construct(IcingaServiceSet $set)
    {
        $this->set = $set;
    }

    /**
     * @return IcingaHost[]
     */
    public static function fetchForServiceSet(IcingaServiceSet $set)
    {
        $loader = new static($set);
        return $loader->fetchServiceSetTargetHosts();
    }

    /**
     * @return IcingaHost[]
     */
    public function fetchServiceSetTargetHosts()
    {
        $hosts = array();
        $filter = Filter::fromQueryString($this->set->get('assign_filter'));
        foreach ($this->fetchAllHosts() as $host) {
            $matcher = HostApplyMatches::prepare($host);
            if ($matcher->matchesFilter($filter)) {
                $hosts[] = $host;
            }
        }
        
        return $hosts;
    }

    /**
     * @return IcingaHost[]
     */
    protected function fetchAllHosts()
    {
        $db = $this->set->getDB();
        $query = $db
            ->select()
            ->from('icinga_host')
            ->where('object_type= ?', "object");

        return IcingaHost::loadAll($this->set->getConnection(), $query);
    }
}
