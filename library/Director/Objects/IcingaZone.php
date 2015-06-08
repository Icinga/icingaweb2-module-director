<?php

namespace Icinga\Module\Director\Objects;

class IcingaZone extends IcingaObject
{
    protected $table = 'icinga_zone';

    protected $defaultProperties = array(
        'id'             => null,
        'object_name'    => null,
        'object_type'    => null,
        'parent_zone_id' => null,
    );

    protected function renderParent_zone_id()
    {
        return $this->renderZoneProperty('parent_zone', $this->parent_zone_id);
    }
}
