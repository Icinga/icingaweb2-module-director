<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaZone extends DbObject
{
    protected $table = 'icinga_zone';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'object_name'    => null,
        'object_type'    => null,
        'parent_zone_id' => null,
    );
}
