<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaZone extends IcingaObject
{
    protected $table = 'icinga_zone';

    protected $defaultProperties = array(
        'id'             => null,
        'object_name'    => null,
        'object_type'    => null,
        'parent_zone_id' => null,
        'is_global'      => 'n',
    );

    protected $booleans = array(
        'is_global' => 'global'
    );

    protected $supportsImports = true;

    protected function renderParent_zone_id()
    {
        return $this->renderZoneProperty($this->parent_zone_id, 'parent_zone');
    }

    protected function renderIs_global()
    {
        // Global is a reserved word in SQL, column name was prefixed
        if ($this->is_global === 'y') {
            return c::renderKeyValue('global', c::renderBoolean($this->is_global));
        } else {
            return '';
        }
    }
}
