<?php

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableCommand extends ObjectsTable
{
    // TODO: external commands? Notifications separately?
    protected $searchColumns = [
        'o.object_name',
        'o.object_type',
        'o.command',
    ];

    protected $columns = [
        'object_name' => 'o.object_name',
        'disabled'    => 'o.disabled',
        'command'     => 'o.command',
    ];

    protected $showColumns = [
        'object_name' => 'Command',
        'command'     => 'Command line'
    ];

    private $objectType;

    public function setType($type)
    {
        $this->getQuery()->where('object_type = ?', $type);
        return $this;
    }

    protected function applyObjectTypeFilter(ZfSelect $query)
    {
        return $query;
    }
}
