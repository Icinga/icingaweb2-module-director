<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Table\Extension\MultiSelect;

class ObjectsTableHost extends ObjectsTable
{
    use MultiSelect;

    protected $type = 'host';

    protected $searchColumns = [
        'o.object_name',
        'o.display_name',
        'o.address',
    ];

    protected $columns = [
        'object_name'  => 'o.object_name',
        'display_name' => 'o.display_name',
        'address'      => 'o.address',
        'disabled'     => 'o.disabled',
        'uuid'         => 'o.uuid',
    ];

    protected $showColumns = [
        'object_name' => 'Hostname',
        'address'     => 'Address'
    ];

    public function assemble()
    {
        $this->enableMultiSelect(
            'director/hosts/edit',
            'director/hosts',
            ['uuid']
        );
    }
}
