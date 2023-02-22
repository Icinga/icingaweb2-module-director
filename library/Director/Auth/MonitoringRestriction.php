<?php

namespace Icinga\Module\Director\Auth;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;

class MonitoringRestriction
{
    public static function getObjectsFilter(Auth $auth): Filter
    {
        $restriction = Filter::matchAny();
        $restriction->setAllowedFilterColumns([
            'host_name',
            'hostgroup_name',
            'instance_name',
            'service_description',
            'servicegroup_name',
            function ($c) {
                return preg_match('/^_(?:host|service)_/i', $c);
            }
        ]);
        foreach ($auth->getRestrictions(Restriction::MONITORING_RW_OBJECT_FILTER) as $filter) {
            if ($filter === '*') {
                return Filter::matchAll();
            }
            $restriction->addFilter(Filter::fromQueryString($filter));
        }

        if ($restriction->isEmpty()) {
            return Filter::matchAll();
        }

        return $restriction;
    }
}
