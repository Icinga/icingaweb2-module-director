<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;

class HostApplyMatches
{
    protected static $flatObjects;

    protected static $columnMap = array(
        'name' => 'object_name'
    );

    /**
     * @param Filter $filter
     * @param Db $db
     *
     * @return array
     */
    public static function forFilter(Filter $filter, Db $db)
    {
        $result = array();
        Benchmark::measure(sprintf('Starting Filter %s', $filter));
        $filter = clone($filter);
        static::fixFilterColumns($filter);
        foreach (static::flatObjects($db) as $object) {
            if ($filter->matches($object)) {
                $name = $object->object_name;
                $result[] = $name;
            }
        }
        Benchmark::measure(sprintf('Got %d results for %s', count($result), $filter));

        return array_values($result);
    }

    protected static function flatObjects(Db $db)
    {
        if (self::$flatObjects === null) {
            self::$flatObjects = static::fetchFlatObjects($db);
        }

        return self::$flatObjects;
    }

    protected static function fetchFlatObjects(Db $db)
    {
        Benchmark::measure('HostApplyMatches: prefetching');
        PrefetchCache::initialize($db);
        $all = IcingaHost::prefetchAll($db);
        IcingaZone::prefetchAll($db);
        IcingaCommand::prefetchAll($db);
        Benchmark::measure('HostApplyMatches: preparing flat objects');

        $objects = array();
        foreach ($all as $host) {
            if ($host->isTemplate()) {
                continue;
            }
            $object = $host->toPlainObject(true);
            static::flattenVars($object);
            $objects[$host->getObjectName()] = $object;
        }
        Benchmark::measure('HostApplyMatches: cache ready');

        return $objects;
    }

    protected static function fixFilterColumns(Filter $filter)
    {
        if ($filter->isExpression()) {
            /** @var FilterExpression $filter */
            $col = $filter->getColumn();
            if (substr($col, 0, 5) === 'host.') {
                $filter->setColumn($col = substr($col, 5));
            }
            if (array_key_exists($col, self::$columnMap)) {
                $filter->setColumn(self::$columnMap[$col]);
            }
            $filter->setExpression(json_decode($filter->getExpression()));
        } else {
            foreach ($filter->filters() as $sub) {
                static::fixFilterColumns($sub);
            }
        }
    }

    protected static function flattenVars(& $object, $key = 'vars')
    {
        if (property_exists($object, 'vars')) {
            foreach ($object->vars as $k => $v) {
                if (is_object($v)) {
                    static::flattenVars($v, $k);
                }
                $object->{$key . '.' . $k} = $v;
            }
            unset($object->vars);
        }
    }

    protected function __construct()
    {
    }
}