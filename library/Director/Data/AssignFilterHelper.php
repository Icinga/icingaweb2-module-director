<?php

namespace Icinga\Module\Director\Data;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterNot;
use Icinga\Data\Filter\FilterOr;
use Icinga\Exception\NotImplementedError;

/**
 * Class ApplyFilterMatches
 *
 * A wrapper for Icinga Filter to evaluate filters against Director's objects
 */
class AssignFilterHelper
{
    /** @var Filter */
    protected $filter;

    public function __construct(Filter $filter)
    {
        $this->filter = $filter;
    }

    /**
     * @param object $object
     *
     * @return bool
     * @throws NotImplementedError
     */
    public function matches($object)
    {
        return $this->matchesPart($this->filter, $object);
    }

    /**
     * @param Filter $filter
     * @param object $object
     *
     * @return bool
     */
    public static function matchesFilter(Filter $filter, $object)
    {
        $helper = new static($filter);
        return $helper->matches($object);
    }

    /**
     * @param Filter $filter
     * @param object $object
     *
     * @return bool
     * @throws NotImplementedError
     */
    protected function matchesPart(Filter $filter, $object)
    {
        if ($filter->isChain()) {
            return $this->matchesChain($filter, $object);
        } elseif ($filter->isExpression()) {
            /** @var FilterExpression $filter */
            return $this->matchesExpression($filter, $object);
        } else {
            return $filter->matches($object);
        }
    }

    /**
     * @param Filter $filter
     * @param object $object
     *
     * @return bool
     * @throws NotImplementedError
     */
    protected function matchesChain(Filter $filter, $object)
    {
        if ($filter instanceof FilterAnd) {
            foreach ($filter->filters() as $f) {
                if (! $this->matchesPart($f, $object)) {
                    return false;
                }
            }

            return true;
        } elseif ($filter instanceof FilterOr) {
            foreach ($filter->filters() as $f) {
                if ($this->matchesPart($f, $object)) {
                    return true;
                }
            }

            return false;
        } elseif ($filter instanceof FilterNot) {
            foreach ($filter->filters() as $f) {
                if ($this->matchesPart($f, $object)) {
                    return false;
                }
            }

            return true;
        } else {
            $class = \get_class($filter);
            $parts = \preg_split('/\\\/', $class);

            throw new NotImplementedError(
                'Matching for Filter of type "%s" is not implemented',
                \end($parts)
            );
        }
    }

    /**
     * @param FilterExpression $filter
     * @param object           $object
     *
     * @return bool
     */
    protected function matchesExpression(FilterExpression $filter, $object)
    {
        $column = $filter->getColumn();
        $sign = $filter->getSign();
        $expression = $filter->getExpression();

        if ($sign === '=') {
            if ($expression === true) {
                return property_exists($object, $column) && ! empty($object->{$column});
            } elseif ($expression === false) {
                return ! property_exists($object, $column) || empty($object->{$column});
            } elseif (is_string($expression) && strpos($expression, '*') !== false) {
                if (! property_exists($object, $column) || empty($object->{$column})) {
                    return false;
                }
                $value = $object->{$column};

                $parts = array();
                foreach (preg_split('~\*~', $expression) as $part) {
                    $parts[] = preg_quote($part);
                }
                // match() is case insensitive
                $pattern = '/^' . implode('.*', $parts) . '$/i';

                if (is_array($value)) {
                    foreach ($value as $candidate) {
                        if (preg_match($pattern, $candidate)) {
                            return true;
                        }
                    }

                    return false;
                }

                return (bool) preg_match($pattern, $value);
            }
        }

        // fallback to default behavior
        return $filter->matches($object);
    }
}
