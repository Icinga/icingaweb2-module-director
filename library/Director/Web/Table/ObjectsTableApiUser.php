<?php

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableApiUser extends ObjectsTable
{
    protected function applyObjectTypeFilter(ZfSelect $query, ?ZfSelect $right = null)
    {
        return $query->where("o.object_type IN ('object', 'external_object')");
    }
}
