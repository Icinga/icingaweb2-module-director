<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class IcingaServiceVar extends IcingaObject
{
    protected $keyName = array('service_id', 'varname');

    protected $table = 'icinga_service_var';

    protected $defaultProperties = array(
        'service_id'   => null,
        'varname'   => null,
        'varvalue'  => null,
        'format'    => null,
    );

    public function onInsert()
    {
    }

    public function onUpdate()
    {
    }

    public function onDelete()
    {
    }
}
