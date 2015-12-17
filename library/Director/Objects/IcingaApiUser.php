<?php

namespace Icinga\Module\Director\Objects;

class IcingaApiUser extends IcingaObject
{
    protected $table = 'icinga_apiuser';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'password'              => null,
        'client_dn'             => null,
        'permissions'           => null,
    );
}
