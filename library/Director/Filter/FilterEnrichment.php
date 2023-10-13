<?php

namespace Icinga\Module\Director\Filter;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;

class FilterEnrichment
{
    public static function enrichFilter(Filter $filter): Filter
    {
        if ($filter instanceof FilterExpression) {
            if (CidrExpression::isCidrFormat($filter->getExpression())) {
                return CidrExpression::fromExpression($filter);
            }
        } elseif ($filter instanceof FilterChain) {
            foreach ($filter->filters() as $subFilter) {
                if ($subFilter instanceof FilterExpression
                    && CidrExpression::isCidrFormat($subFilter->getExpression())
                ) {
                    $filter->replaceById($subFilter->getId(), CidrExpression::fromExpression($subFilter));
                }
            }
        }

        return $filter;
    }
}
