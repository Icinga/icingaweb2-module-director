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
        // Global is a reserved word in SQL, column name was prefixed
        'is_global' => 'global'
    );

    protected $relations = array(
        'parent_zone' => 'IcingaZone',
    );

    protected $supportsImports = true;

    protected function renderParent_zone_id()
    {
        return $this->renderRelationProperty(
            'parent_zone',
            $this->parent_zone_id,
            'parent'
        );
    }
}
