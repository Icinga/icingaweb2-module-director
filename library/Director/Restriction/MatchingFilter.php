<?php

namespace Icinga\Module\Director\Restriction;

use Icinga\Data\Filter\Filter;
use Icinga\User;

class MatchingFilter
{
    public static function forPatterns(array $restrictions, $columnName)
    {
        $filters = [];
        foreach ($restrictions as $restriction) {
            foreach (preg_split('/\|/', $restriction) as $pattern) {
                $filters[] = Filter::expression(
                    $columnName,
                    '=',
                    $pattern
                );
            }
        }

        if (count($filters) === 1) {
            return $filters[0];
        } else {
            return Filter::matchAny($filters);
        }
    }

    public static function forUser(
        User $user,
        $restrictionName,
        $columnName
    ) {
        return static::forPatterns(
            $user->getRestrictions($restrictionName),
            $columnName
        );
    }
}
