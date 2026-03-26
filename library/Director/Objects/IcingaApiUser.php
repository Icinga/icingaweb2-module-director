<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaApiUser extends IcingaObject
{
    protected $table = 'icinga_apiuser';

    protected $uuidColumn = 'uuid';

    // TODO: Enable (and add table) if required
    protected $supportsImports = false;

    protected $defaultProperties = [
        'id'                    => null,
        'uuid'                  => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'password'              => null,
        'client_dn'             => null,
        'permissions'           => null,
    ];

    protected function renderPassword()
    {
        return c::renderKeyValue('password', c::renderString('***'));
    }
}
