<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaUserGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_usergroup';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'zone_id'       => null,
    ];

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    protected function prefersGlobalZone()
    {
        return false;
    }
}
