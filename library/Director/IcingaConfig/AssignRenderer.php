<?php

namespace Icinga\Module\Director\IcingaConfig;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterEqualOrGreaterThan;
use Icinga\Data\Filter\FilterEqualOrLessThan;
use Icinga\Data\Filter\FilterEqual;
use Icinga\Data\Filter\FilterGreaterThan;
use Icinga\Data\Filter\FilterLessThan;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Data\Filter\FilterMatchNot;
use Icinga\Data\Filter\FilterNotEqual;
use Icinga\Exception\QueryException;
use Icinga\Module\Director\Data\Json;
use InvalidArgumentException;

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
        if ($filter instanceof FilterNot) {
            $parts = [];
            foreach ($filter->filters() as $sub) {
                $parts[] = $this->renderFilter($sub);
            }

            return '!(' . implode(' && ', $parts) . ')';
        }
        if ($filter->isChain()) {
            /** @var FilterChain $filter */
            return $this->renderFilterChain($filter);
        } else {
            /** @var FilterExpression $filter */
            return $this->renderFilterExpression($filter);
        }
    }

    protected function renderEquals($column, $expression)
    {
        if (substr($column, -7) === '.groups') {
            return sprintf(
                '%s in %s',
                $expression,
                $column
            );
        } else {
            return sprintf(
                '%s == %s',
                $column,
                $expression
            );
        }
    }

    protected function renderNotEquals($column, $expression)
    {
        if (substr($column, -7) === '.groups') {
            return sprintf(
                '!(%s in %s)',
                $expression,
                $column
            );
        } else {
            return sprintf(
                '%s != %s',
                $column,
                $expression
            );
        }
    }

    protected function renderInArray($column, $expression)
    {
        return sprintf(
            '%s in %s',
            $column,
            $expression
        );
    }

    protected function renderContains(FilterExpression $filter)
    {
        return sprintf(
            '%s in %s',
            $this->renderExpressionValue(json_decode($filter->getColumn())),
            $filter->getExpression()
        );
    }

    protected function renderFilterExpression(FilterExpression $filter)
    {
        if ($this->columnIsJson($filter)) {
            return $this->renderContains($filter);
        }

        $column = $filter->getColumn();
        try {
            $rawExpression = JsonString::decode($filter->getExpression());
            $expression = $this->renderExpressionValue($rawExpression);
        } catch (JsonDecodeException $e) {
            throw new InvalidArgumentException(
                "Got invalid JSON in filter string: $column" . $filter->getSign() . $filter->getExpression()
            );
        }

        if (is_array($rawExpression) && $filter instanceof FilterMatch) {
            return $this->renderInArray($column, $expression);
        }

        if (is_string($rawExpression) && ctype_digit($rawExpression)) {
            // TODO: doing this for compat reasons, should work for all filters
            if (
                $filter instanceof FilterEqualOrGreaterThan
                || $filter instanceof FilterGreaterThan
                || $filter instanceof FilterEqualOrLessThan
                || $filter instanceof FilterLessThan
            ) {
                $expression = $rawExpression;
            }
        }

        if ($filter instanceof FilterEqual) {
            if (is_array($rawExpression)) {
                return sprintf(
                    '%s in %s',
                    $column,
                    $expression
                );
            } else {
                return sprintf(
                    '%s == %s',
                    $column,
                    $expression
                );
            }
        } elseif ($filter instanceof FilterMatch) {
            if ($rawExpression === true) {
                return $column;
            }
            if ($rawExpression === false) {
                return sprintf(
                    '! %s',
                    $column
                );
            }
            if (strpos($expression, '*') === false) {
                return $this->renderEquals($column, $expression);
            } else {
                return sprintf(
                    'match(%s, %s)',
                    $expression,
                    $column
                );
            }
        } elseif ($filter instanceof FilterMatchNot) {
            if (strpos($expression, '*') === false) {
                return $this->renderNotEquals($column, $expression);
            } else {
                return sprintf(
                    '! match(%s, %s)',
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

    protected function renderExpressionValue($value)
    {
        return IcingaConfigHelper::renderPhpValue($value);
    }

    protected function columnIsJson(FilterExpression $filter)
    {
        $col = $filter->getColumn();
        return strlen($col) && $col[0] === '"';
    }

    protected function renderFilterChain(FilterChain $filter)
    {
        // TODO: brackets if deeper level?
        if ($filter instanceof FilterAnd) {
            $op = ' && ';
        } elseif ($filter instanceof FilterOr) {
            $op = ' || ';
        } elseif ($filter instanceof FilterNot) {
            throw new InvalidArgumentException('renderFilterChain should never get a FilterNot instance');
        } else {
            throw new InvalidArgumentException('Cannot render filter: %s', $filter);
        }

        $parts = array();
        if (! $filter->isEmpty()) {
            /** @var Filter $f */
            foreach ($filter->filters() as $f) {
                if ($f instanceof FilterChain && $f->count() > 1) {
                    $parts[] = '(' . $this->renderFilter($f) . ')';
                } else {
                    $parts[] = $this->renderFilter($f);
                }
            }
        }

        return implode($op, $parts);
    }
}
