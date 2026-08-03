<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;

/**
 * Finds _override_servicevars keys that don't match any service a host could have.
 * Meant as an early UI hint, not strict validation.
 *
 * An apply rule whose apply_for target isn't a registered array/dictionary
 * property gets a runtime-computed override key we can't predict here. So a
 * correct key can still show up as unmatched in that one case.
 */
class OverrideServiceVarsKeyValidator
{
    /** @var array<string, string[]> Cached per request, keyed by host name */
    protected static array $cache = [];

    /**
     * @return string[] Unmatched keys, empty if none
     */
    public static function findUnmatchedKeys(IcingaHost $host): array
    {
        if ($host->isTemplate() || ! $host->hasAnyOverridenServiceVars()) {
            return [];
        }

        // Keyed by name, not object id. Object ids get reused after GC.
        $cacheKey = $host->getObjectName();
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $keys = array_keys((array) $host->getAllOverriddenServiceVars());
        $candidates = static::candidateServiceNames($host);

        return self::$cache[$cacheKey] = array_values(array_diff($keys, $candidates));
    }

    /**
     * @return string[]
     */
    protected static function candidateServiceNames(IcingaHost $host): array
    {
        $names = [];

        foreach ($host->fetchServices() as $service) {
            $names[] = $service->getObjectName();
        }

        foreach ($host->fetchServiceSets() as $set) {
            foreach ($set->fetchServices() as $service) {
                $names[] = $service->getObjectName();
            }
        }

        foreach (AppliedServiceSetLoader::fetchForHost($host) as $set) {
            foreach ($set->fetchServices() as $service) {
                $names[] = $service->getObjectName();
            }
        }

        $matcher = HostApplyMatches::prepare($host);
        foreach (static::fetchApplyRules($host) as $rule) {
            $filter = Filter::fromQueryString($rule->get('assign_filter'));
            if ($matcher->matchesFilter($filter)) {
                // Raw, unexpanded name. That's what vars.overriddenVar becomes when
                // apply_for targets a registered array/dictionary property.
                $names[] = $rule->getObjectName();
            }
        }

        return array_unique($names);
    }

    /**
     * @return IcingaService[]
     */
    protected static function fetchApplyRules(IcingaHost $host): array
    {
        $db = $host->getDb();
        $query = $db
            ->select()
            ->from('icinga_service')
            ->where('object_type = ?', 'apply')
            ->where('assign_filter IS NOT NULL');

        return IcingaService::loadAll($host->getConnection(), $query);
    }
}
