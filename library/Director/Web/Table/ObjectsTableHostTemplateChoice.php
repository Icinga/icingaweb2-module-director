<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableHostTemplateChoice extends ObjectsTable
{
    protected $columns = [
        'object_name' => 'o.object_name',
        'templates'   => 'GROUP_CONCAT(t.object_name)'
    ];

    protected function applyObjectTypeFilter(ZfSelect $query, ZfSelect $right = null)
    {
        return $query;
    }

    protected function prepareQuery()
    {
        return parent::prepareQuery()->joinLeft(
            ['t' => 'icinga_host'],
            't.template_choice_id = o.id',
            []
        )->group('o.id');
    }
}
