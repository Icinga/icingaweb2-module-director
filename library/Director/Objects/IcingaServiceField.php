<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaServiceField extends DbObject
{
    protected $keyName = array('service_id', 'datafield_id');

    protected $table = 'icinga_service_field';

    protected $defaultProperties = array(
        'service_id'       => null,
        'datafield_id'  => null,
        'is_required'   => null
    );
}
