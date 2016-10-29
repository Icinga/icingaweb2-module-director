<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class SyncProperty extends DbObject
{
    protected $table = 'sync_property';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                => null,
        'rule_id'           => null,
        'source_id'         => null,
        'source_expression' => null,
        'destination_field' => null,
        'priority'          => null,
        'filter_expression' => null,
        'merge_policy'      => null
    );
}
