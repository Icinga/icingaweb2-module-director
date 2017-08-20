<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\Extension\PriorityColumn;

class SyncProperty extends DbObject
{
    use PriorityColumn;

    protected $table = 'sync_property';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'                => null,
        'rule_id'           => null,
        'source_id'         => null,
        'source_expression' => null,
        'destination_field' => null,
        'priority'          => null,
        'filter_expression' => null,
        'merge_policy'      => null
    ];

    protected function beforeStore()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            $this->setNextPriority('rule_id');
        }
    }

    protected function onInsert()
    {
        $this->refreshPriortyProperty();
    }
}
