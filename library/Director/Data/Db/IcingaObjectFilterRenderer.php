<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterException;
use Icinga\Data\Filter\FilterExpression;

class IcingaObjectFilterRenderer
{
    /** @var Filter */
    protected $filter;

    /** @var IcingaObjectQuery */
    protected $query;

    protected $columnMap = [
        'host.name' => 'host.object_name',
        'service.name' => 'service.object_name',
    ];

    public function __construct(Filter $filter, IcingaObjectQuery $query)
    {
        $this->filter = clone($filter);
        $this->fixFilterColumns($this->filter);
        $this->query = $query;
    }

    /**
     * @param Filter $filter
     * @param IcingaObjectQuery $query
     *
     * @return IcingaObjectQuery
     */
    public static function apply(Filter $filter, IcingaObjectQuery $query)
    {
        $self = new static($filter, $query);
        return $self->applyFilterToQuery();
    }

    /**
     * @return IcingaObjectQuery
     */
    protected function applyFilterToQuery()
    {
        $this->query->escapedWhere($this->renderFilter($this->filter));
        return $this->query;
    }

    /**
     * @param Filter $filter
     * @return string
     */
    protected function renderFilter(Filter $filter)
    {
        if ($filter->isChain()) {
            /** @var FilterChain $filter */
            return $this->renderFilterChain($filter);
        } else {
            /** @var FilterExpression $filter */
            return $this->renderFilterExpression($filter);
        }
    }

    /**
     * @param FilterChain $filter
     *
     * @throws FilterException
     *
     * @return string
     */
    protected function renderFilterChain(FilterChain $filter)
    {
        $parts = array();
        foreach ($filter->filters() as $sub) {
            $parts[] = $this->renderFilter($sub);
        }

        $op = $filter->getOperatorName();
        if ($op === 'NOT') {
            if (count($parts) !== 1) {
                throw new FilterException(
                    'NOT should have exactly one child, got %s',
                    count($parts)
                );
            }

            return $op . ' ' . $parts[0];
        } else {
            if ($filter->isRootNode()) {
                return implode(' ' . $op . ' ', $parts);
            } else {
                return '(' . implode(' ' . $op . ' ', $parts) . ')';
            }
        }
    }

    protected function fixFilterColumns(Filter $filter)
    {
        if ($filter->isExpression()) {
            /** @var FilterExpression $filter */
            $col = $filter->getColumn();
            if (array_key_exists($col, $this->columnMap)) {
                $filter->setColumn($this->columnMap[$col]);
            }
            if (strpos($col, 'vars.') === false) {
                $filter->setExpression(json_decode($filter->getExpression()));
            }
        } else {
            /** @var FilterChain $filter */
            foreach ($filter->filters() as $sub) {
                $this->fixFilterColumns($sub);
            }
        }
    }

    /**
     * @param FilterExpression $filter
     *
     * @return string
     */
    protected function renderFilterExpression(FilterExpression $filter)
    {
        $query = $this->query;
        $column = $query->getAliasForRequiredFilterColumn($filter->getColumn());
        return $query->whereToSql(
            $column,
            $filter->getSign(),
            $filter->getExpression()
        );
    }
}
