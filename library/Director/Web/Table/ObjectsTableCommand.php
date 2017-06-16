<?php

namespace Icinga\Module\Director\Web\Table;

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
        'command'     => 'o.command',
    ];

    protected $showColumns = [
        'object_name' => 'Command',
        'command'     => 'Command line'
    ];
}
