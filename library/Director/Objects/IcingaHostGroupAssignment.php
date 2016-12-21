<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroupAssignment extends IcingaObject
{
    protected $table = 'icinga_hostgroup_assignment';

    protected $keyName = 'id';

    protected $defaultProperties = array(
        'id'            => null,
        'service_id'    => null,
        'filter_string' => null,
    );

    protected $relations = array(
        'service' => 'IcingaHostGroup',
    );
}
