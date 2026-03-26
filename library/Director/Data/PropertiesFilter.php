<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data;

class PropertiesFilter
{
    public static $CUSTOM_PROPERTY = 'CUSTOM_PROPERTY';
    public static $HOST_PROPERTY = 'HOST_PROPERTY';
    public static $SERVICE_PROPERTY = 'SERVICE_PROPERTY';

    protected $blacklist = array(
        'id',
        'object_name',
        'object_type',
        'disabled',
        'has_agent',
        'master_should_connect',
        'accept_config',
    );

    public function match($type, $name, $object = null)
    {
        return ($type != self::$HOST_PROPERTY || !in_array($name, $this->blacklist));
    }
}
