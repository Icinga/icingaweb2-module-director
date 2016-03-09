<?php

namespace Icinga\Module\Director\Objects;

abstract class IcingaObjectGroup extends IcingaObject
{
    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
    );
}
