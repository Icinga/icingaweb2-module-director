<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfigHelper as c;

class IcingaCommand extends IcingaObject
{
    protected $table = 'icinga_command';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'methods_execute'       => null,
        'command'               => null,
        'timeout'               => null,
        'zone_id'               => null,
        'object_type'           => null,
    );

    protected function renderMethods_execute()
    {
        // Execute is a reserved word in SQL, column name was prefixed
        return c::renderKeyValue('execute', $this->methods_execute, '    ');
    }
}
