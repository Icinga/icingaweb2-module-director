<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Restriction;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Auth\Restriction;

/**
 * Parse the director/filter/hosts restriction
 *
 * Uses the same query string syntax as the assign filters of apply rules,
 * but only allows the columns a host can be restricted by
 */
class HostFilter
{
    /** @var string[] Columns that can be used as they are */
    public const ALLOWED_COLUMNS = ['host.name', 'hostgroup.name'];

    /** @var string Prefix of the custom variable columns */
    public const VARS_PREFIX = 'host.vars.';

    /**
     * Combine the given restrictions into one filter
     *
     * Every role brings its own restriction, a host matching any of them is allowed
     *
     * @param string[] $restrictions As returned by Auth::getRestrictions()
     *
     * @return ?Filter null if there is nothing to restrict
     *
     * @throws ConfigurationError
     */
    public static function fromRestrictions(array $restrictions): ?Filter
    {
        $filters = [];
        foreach ($restrictions as $restriction) {
            if (trim($restriction) === '') {
                continue;
            }

            $filters[] = static::parse($restriction);
        }

        if (empty($filters)) {
            return null;
        }

        if (count($filters) === 1) {
            return $filters[0];
        }

        return Filter::matchAny($filters);
    }

    /**
     * Parse a single restriction
     *
     * @param string $queryString
     *
     * @return Filter
     *
     * @throws ConfigurationError
     */
    public static function parse(string $queryString): Filter
    {
        $filter = Filter::fromQueryString($queryString);
        static::assertAllowedColumns($filter, $queryString);

        return $filter;
    }

    /**
     * Prepare a parsed restriction for HostApplyMatches
     *
     * HostApplyMatches expects the columns and JSON encoded values of assign
     * filters. The restriction is written like icingadb/filter/hosts instead,
     * so hostgroup.name becomes host.groups, an array marker like team[] or
     * team[*] is dropped, as arrays are matched element-wise anyway, and every
     * value gets JSON encoded.
     *
     * @param Filter $filter As returned by parse() or fromRestrictions()
     *
     * @return Filter A modified copy, the given filter is left untouched
     */
    public static function prepareForMatching(Filter $filter): Filter
    {
        $filter = clone $filter;
        static::prepareFilterForMatching($filter);

        return $filter;
    }

    /**
     * Rewrite the given filter in place, see prepareForMatching()
     *
     * @param Filter $filter
     *
     * @return void
     */
    protected static function prepareFilterForMatching(Filter $filter): void
    {
        if ($filter instanceof FilterChain) {
            foreach ($filter->filters() as $subFilter) {
                static::prepareFilterForMatching($subFilter);
            }

            return;
        }

        if (! $filter instanceof FilterExpression) {
            return;
        }

        $column = $filter->getColumn();
        if ($column === 'hostgroup.name') {
            $column = 'host.groups';
        } elseif (str_ends_with($column, '[]')) {
            $column = substr($column, 0, -2);
        } elseif (str_ends_with($column, '[*]')) {
            $column = substr($column, 0, -3);
        }

        $filter->setColumn($column);
        $filter->setExpression(json_encode($filter->getExpression()));
    }

    /**
     * Make sure the given filter only uses the allowed columns
     *
     * @param Filter $filter
     * @param string $queryString The restriction the filter was parsed from, for the error message
     *
     * @return void
     *
     * @throws ConfigurationError
     */
    protected static function assertAllowedColumns(Filter $filter, string $queryString): void
    {
        if ($filter instanceof FilterChain) {
            foreach ($filter->filters() as $subFilter) {
                static::assertAllowedColumns($subFilter, $queryString);
            }

            return;
        }

        if (! $filter instanceof FilterExpression) {
            return;
        }

        $column = $filter->getColumn();
        if (in_array($column, self::ALLOWED_COLUMNS, true)) {
            return;
        }

        if (str_starts_with($column, self::VARS_PREFIX) && strlen($column) > strlen(self::VARS_PREFIX)) {
            return;
        }

        throw new ConfigurationError(
            'Cannot apply restriction %s using the filter %s. You can only use the following columns: %s',
            Restriction::FILTER_HOSTS,
            $queryString,
            implode(', ', array_merge(self::ALLOWED_COLUMNS, [self::VARS_PREFIX . '<customvar-name>']))
        );
    }
}
