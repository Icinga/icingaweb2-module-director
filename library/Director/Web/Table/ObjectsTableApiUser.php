<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableApiUser extends ObjectsTable
{
    protected function applyObjectTypeFilter(ZfSelect $query, ZfSelect $right = null)
    {
        return $query->where("o.object_type IN ('object', 'external_object')");
    }
}
