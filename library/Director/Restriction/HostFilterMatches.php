<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Restriction;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\AssignFilterHelper;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;

/**
 * Match hosts against a director/filter/hosts restriction
 *
 * Builds on the matching of apply rules. Unlike HostApplyMatches::forFilter(),
 * the hosts also carry the groups they got through a hostgroup's assign filter,
 * like HostApplyMatches::prepare() already does for a single host. So a host
 * is either allowed in the lists and when opened directly, or in neither.
 *
 * Keeps its own cache of flat hosts, apply rule previews are not affected.
 */
class HostFilterMatches extends HostApplyMatches
{
    /** @var ?array<string, object> Flat hosts by name, including their resolved groups */
    protected static $restrictionFlatObjects;

    /**
     * Whether the given host matches the given filter
     *
     * @param IcingaHost $host
     * @param Filter $filter As returned by HostFilter::prepareForMatching()
     *
     * @return bool
     */
    public static function matchesHost(IcingaHost $host, Filter $filter): bool
    {
        return static::prepare($host)->matchesFlatObject($filter);
    }

    /**
     * Get the names of all hosts matching the given filter
     *
     * @param Filter $filter As returned by HostFilter::prepareForMatching()
     * @param Db $db
     *
     * @return string[]
     */
    public static function listMatchingHostNames(Filter $filter, Db $db): array
    {
        $helper = new AssignFilterHelper(static::fixedFilter($filter));

        $result = [];
        foreach (static::restrictionFlatObjects($db) as $object) {
            if ($helper->matches($object)) {
                $result[] = $object->object_name;
            }
        }

        return $result;
    }

    /**
     * Forget the cached flat hosts
     *
     * @return void
     */
    public static function clearCache(): void
    {
        static::$restrictionFlatObjects = null;
    }

    /**
     * Whether this instance's flat host matches the given filter
     *
     * @param Filter $filter
     *
     * @return bool
     */
    protected function matchesFlatObject(Filter $filter): bool
    {
        return AssignFilterHelper::matchesFilter(static::fixedFilter($filter), $this->flatObject);
    }

    /**
     * Get a copy of the given filter with the columns fixed for flat objects
     *
     * Deliberately not cached like getPreparedFilter() does, that cache is
     * keyed by spl_object_hash(), which PHP reuses once a filter is gone.
     *
     * @param Filter $filter
     *
     * @return Filter
     */
    protected static function fixedFilter(Filter $filter): Filter
    {
        $filter = clone $filter;
        static::fixFilterColumns($filter);

        return $filter;
    }

    /**
     * Get all flat hosts, including the groups resolved for them
     *
     * @param Db $db
     *
     * @return array<string, object>
     */
    protected static function restrictionFlatObjects(Db $db): array
    {
        if (static::$restrictionFlatObjects === null) {
            $wasPrefetching = PrefetchCache::shouldBeUsed();
            $objects = static::fetchFlatObjects($db);
            if (! $wasPrefetching) {
                // fetchFlatObjects() leaves the prefetch cache turned on, objects loaded or
                // stored later on in this request would then work with this snapshot
                PrefetchCache::forget();
            }

            foreach (static::fetchResolvedGroups($db) as $hostName => $groups) {
                if (! isset($objects[$hostName])) {
                    continue;
                }

                $object = $objects[$hostName];
                $object->groups = array_values(array_unique(array_merge(
                    (array) ($object->groups ?? []),
                    $groups
                )));
            }

            static::$restrictionFlatObjects = $objects;
        }

        return static::$restrictionFlatObjects;
    }

    /**
     * Get the resolved hostgroup names of all hosts
     *
     * @param Db $db
     *
     * @return array<string, string[]> Group names by host name
     */
    protected static function fetchResolvedGroups(Db $db): array
    {
        $dba = $db->getDbAdapter();
        $query = $dba->select()->from(
            ['hgh' => 'icinga_hostgroup_host_resolved'],
            []
        )->join(
            ['h' => 'icinga_host'],
            'h.id = hgh.host_id',
            ['host_name' => 'h.object_name']
        )->join(
            ['hg' => 'icinga_hostgroup'],
            'hg.id = hgh.hostgroup_id',
            ['group_name' => 'hg.object_name']
        );

        $result = [];
        foreach ($dba->fetchAll($query) as $row) {
            $result[$row->host_name][] = $row->group_name;
        }

        return $result;
    }
}
