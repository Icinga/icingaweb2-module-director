<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Data\AssignFilterHelper;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use stdClass;

abstract class ObjectApplyMatches
{
    protected static $flatObjects;

    protected static $columnMap = array(
        'name' => 'object_name'
    );

    protected $object;

    protected $flatObject;

    protected static $type;

    protected static $preparedFilters = array();

    public static function prepare(IcingaObject $object)
    {
        return new static($object);
    }

    /**
     * Prepare a Filter with fixed columns, and store the result
     *
     * @param Filter $filter
     *
     * @return Filter
     */
    protected static function getPreparedFilter(Filter $filter)
    {
        $hash = spl_object_hash($filter);
        if (! array_key_exists($hash, self::$preparedFilters)) {
            $filter = clone($filter);
            static::fixFilterColumns($filter);
            self::$preparedFilters[$hash] = $filter;
        }
        return self::$preparedFilters[$hash];
    }

    public function matchesFilter(Filter $filter)
    {
        $filterObj = static::getPreparedFilter($filter);
        if ($filterObj->isExpression() || ! $filterObj->isEmpty()) {
            return AssignFilterHelper::matchesFilter($filterObj, $this->flatObject);
        } else {
            return false;
        }
    }

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
        $helper = new AssignFilterHelper($filter);

        foreach (static::flatObjects($db) as $object) {
            if ($helper->matches($object)) {
                $name = $object->object_name;
                $result[] = $name;
            }
        }
        Benchmark::measure(sprintf('Got %d results for %s', count($result), $filter));

        return array_values($result);
    }

    protected static function getType()
    {
        if (static::$type === null) {
            throw new ProgrammingError(
                'Implementations of %s need ::$type to be defined, %s has not',
                __CLASS__,
                get_called_class()
            );
        }

        return static::$type;
    }

    protected static function flatObjects(Db $db)
    {
        if (self::$flatObjects === null) {
            self::$flatObjects = static::fetchFlatObjects($db);
        }

        return self::$flatObjects;
    }

    protected static function raiseLimits()
    {
        // Note: IcingaConfig also raises the limit for generation, **but** we
        // need the higher limit for preview.
        MemoryLimit::raiseTo('1024M');
    }

    protected static function fetchFlatObjects(Db $db)
    {
        return static::fetchFlatObjectsByType($db, static::getType());
    }

    protected static function fetchFlatObjectsByType(Db $db, $type)
    {
        self::raiseLimits();

        Benchmark::measure("ObjectApplyMatches: prefetching $type");
        PrefetchCache::initialize($db);
        /** @var IcingaObject $class */
        $class = DbObjectTypeRegistry::classByType($type);
        $all = $class::prefetchAll($db);
        Benchmark::measure("ObjectApplyMatches: related objects for $type");
        $class::prefetchAllRelationsByType($type, $db);
        Benchmark::measure("ObjectApplyMatches: preparing flat $type objects");

        $objects = array();
        foreach ($all as $object) {
            if ($object->isTemplate()) {
                continue;
            }

            $flat = $object->toPlainObject(true, false);
            static::flattenVars($flat);
            $objects[$object->getObjectName()] = $flat;
        }
        Benchmark::measure("ObjectApplyMatches: $type cache ready");

        return $objects;
    }

    public static function fixFilterColumns(Filter $filter)
    {
        if ($filter->isExpression()) {
            /** @var FilterExpression $filter */
            static::fixFilterExpressionColumn($filter);
        } else {
            foreach ($filter->filters() as $sub) {
                static::fixFilterColumns($sub);
            }
        }
    }

    protected static function fixFilterExpressionColumn(FilterExpression $filter)
    {
        if (static::columnIsJson($filter)) {
            $column = $filter->getExpression();
            $filter->setExpression($filter->getColumn());
            $filter->setColumn($column);
        }

        $col = $filter->getColumn();
        $type = static::$type;

        if ($type && substr($col, 0, strlen($type) + 1) === "{$type}.") {
            $filter->setColumn($col = substr($col, strlen($type) + 1));
        }

        if (array_key_exists($col, self::$columnMap)) {
            $filter->setColumn(self::$columnMap[$col]);
        }

        $filter->setExpression(json_decode($filter->getExpression()));
    }

    protected static function columnIsJson(FilterExpression $filter)
    {
        $col = $filter->getColumn();
        return strlen($col) && $col[0] === '"';
    }

    /**
     * Helper, flattens all vars of a given object
     *
     * The object itself will be modified, and the 'vars' property will be
     * replaced with corresponding 'vars.whatever' properties
     *
     * @param $object
     * @param string $key
     */
    protected static function flattenVars(stdClass $object, $key = 'vars')
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

    protected function __construct(IcingaObject $object)
    {
        $this->object = $object;
        $flat = $object->toPlainObject(true, false);
        // Sure, we are flat - but we might still want to match templates.
        unset($flat->imports);
        $flat->templates = $object->listFlatResolvedImportNames();
        $this->addAppliedGroupsToFlatObject($flat, $object);
        static::flattenVars($flat);
        $this->flatObject = $flat;
    }

    protected function addAppliedGroupsToFlatObject($flat, IcingaObject $object)
    {
        if ($object instanceof IcingaHost) {
            $appliedGroups = $object->getAppliedGroups();
            if (! empty($appliedGroups)) {
                if (isset($flat->groups)) {
                    $flat->groups = array_merge($flat->groups, $appliedGroups);
                } else {
                    $flat->groups = $appliedGroups;
                }
            }
        }
    }
}
