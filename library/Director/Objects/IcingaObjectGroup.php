<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

abstract class IcingaObjectGroup extends IcingaObject
{
    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'            => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'assign_filter' => null,
    );

    public function getRenderingZone(IcingaConfig $config = null)
    {
        return $this->connection->getDefaultGlobalZoneName();
    }
}
