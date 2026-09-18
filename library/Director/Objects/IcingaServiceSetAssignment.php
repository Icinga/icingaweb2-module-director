<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaServiceSetAssignment extends IcingaObject
{
    protected $table = 'icinga_service_set_assignment';

    protected $keyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'service_set_id' => null,
        'filter_string'  => null,
    );

    protected $relations = array(
        'service_set' => 'IcingaServiceSet',
    );
}
