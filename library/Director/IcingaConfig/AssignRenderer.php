<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterOr;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterEqualOrGreaterThan;
use Icinga\Data\Filter\FilterEqualOrLessThan;
use Icinga\Data\Filter\FilterEqual;
use Icinga\Data\Filter\FilterGreaterThan;
use Icinga\Data\Filter\FilterLessThan;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Data\Filter\FilterNotEqual;
use Icinga\Exception\QueryException;

class AssignRenderer
{
    protected $filter;

    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }

    public static function forFilter(Filter $filter)
    {
        return new static($filter);
    }

    public function renderAssign()
    {
        return $this->render('assign');
    }

    public function renderIgnore()
    {
        return $this->render('ignore');
    }

    public function render($type)
    {
        return $type . ' where ' . $this->renderFilter($this->filter);
    }

    protected function renderFilter(Filter $filter)
    {
        if ($filter->isChain()) {
            return $this->renderFilterChain($filter);
        } else {
            return $this->renderFilterExpression($filter);
        }
    }

    protected function renderFilterExpression($filter)
    {
        $column = $filter->getColumn();
        $expression = $filter->getExpression();
        if ($filter instanceof FilterEqual) {
            return sprintf(
                '%s == %s',
                $column,
                $expression
            );

        } elseif ($filter instanceof FilterMatch) {
            if (strpos($expression, '*') === false) {
                return sprintf(
                    '%s == %s',
                    $column,
                    $expression
                );
            } else {
                return sprintf(
                    'match(%s, %s)',
                    $expression,
                    $column
                );
            }

        } elseif ($filter instanceof FilterNotEqual) {
                return sprintf(
                    '%s != %s',
                    $column,
                    $expression
                );

        } elseif ($filter instanceof FilterEqualOrGreaterThan) {
                return sprintf(
                    '%s >= %s',
                    $column,
                    $expression
                );

        } elseif ($filter instanceof FilterEqualOrLessThan) {
                return sprintf(
                    '%s <= %s',
                    $column,
                    $expression
                );

        } elseif ($filter instanceof FilterGreaterThan) {
                return sprintf(
                    '%s > %s',
                    $column,
                    $expression
                );

        } elseif ($filter instanceof FilterLessThan) {
                return sprintf(
                    '%s < %s',
                    $column,
                    $expression
                );

        } else {
            throw new QueryException(
                'Filter expression of type "%s" is not supported',
                get_class($filter)
            );
        }
    }

    protected function renderFilterChain(Filter $filter)
    {
        // TODO: brackets if deeper level?
        if ($filter instanceof FilterAnd) {
            $op = ' && ';
        } elseif ($filter instanceof FilterOr) {
            $op = ' || ';
        } elseif ($filter instanceof FilterNot) {
            $op = ' !'; // TODO -> different
        } else {
            throw new QueryException('Cannot render filter: %s', $filter);
        }

        $parts = array();
        if (! $filter->isEmpty()) {
            foreach ($filter->filters() as $f) {
                if ($f->isChain()) {
                    if ($f instanceof FilterNot) {
                        $parts[] = '! (' . $this->renderFilter($f) . ')';
                    } else {
                        $parts[] = '(' . $this->renderFilter($f) . ')';
                    }
                } else {
                    $parts[] = $this->renderFilter($f);
                }
            }
        }

        if ($filter instanceof FilterNot) {
            return implode(' && ', $parts);
        } else {
            return implode($op, $parts);
        }
    }
}
