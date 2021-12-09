<?php

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Select as ZfSelect;

class ObjectsTableCommand extends ObjectsTable implements FilterableByUsage
{
    // TODO: Notifications separately?
    protected $searchColumns = [
        'o.object_name',
        'o.command',
    ];

    protected $columns = [
        'uuid'        => 'o.uuid',
        'object_name' => 'o.object_name',
        'object_type' => 'o.object_type',
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

    public function showOnlyUsed()
    {
        $this->getQuery()->where(
            '('
            . 'EXISTS (SELECT check_command_id FROM icinga_host WHERE check_command_id = o.id)'
            . ' OR EXISTS (SELECT check_command_id FROM icinga_service WHERE check_command_id = o.id)'
            . ' OR EXISTS (SELECT event_command_id FROM icinga_host WHERE event_command_id = o.id)'
            . ' OR EXISTS (SELECT event_command_id FROM icinga_service WHERE event_command_id = o.id)'
            . ' OR EXISTS (SELECT command_id FROM icinga_notification WHERE command_id = o.id)'
            . ')'
        );
    }

    public function showOnlyUnUsed()
    {
        $this->getQuery()->where(
            '('
            . 'NOT EXISTS (SELECT check_command_id FROM icinga_host WHERE check_command_id = o.id)'
            . ' AND NOT EXISTS (SELECT check_command_id FROM icinga_service WHERE check_command_id = o.id)'
            . ' AND NOT EXISTS (SELECT event_command_id FROM icinga_host WHERE event_command_id = o.id)'
            . ' AND NOT EXISTS (SELECT event_command_id FROM icinga_service WHERE event_command_id = o.id)'
            . ' AND NOT EXISTS (SELECT command_id FROM icinga_notification WHERE command_id = o.id)'
            . ')'
        );
    }

    protected function applyObjectTypeFilter(ZfSelect $query, ZfSelect $right = null)
    {
        return $query;
    }
}
