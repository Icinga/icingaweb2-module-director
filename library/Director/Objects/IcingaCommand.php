<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaCommand extends DbObject
{
    protected $table = 'icinga_command';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'methods_execute'       => null,
        'command'               => null,
        'timeout'               => null,
        'zone_id'               => null,
        'object_type'           => null,
    );
}
