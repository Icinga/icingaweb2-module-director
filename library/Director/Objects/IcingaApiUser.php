<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaApiUser extends IcingaObject
{
    protected $table = 'icinga_apiuser';

    // TODO: Enable (and add table) if required
    protected $supportsImports = false;

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'password'              => null,
        'client_dn'             => null,
        'permissions'           => null,
    );

    protected function renderPassword()
    {
        return c::renderKeyValue('password', c::renderString('***'));
    }
}
