<?php

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableZone extends ObjectsTable
{
    protected function applyObjectTypeFilter(ZfSelect $query, ZfSelect $right = null)
    {
        return $query;
    }
}
