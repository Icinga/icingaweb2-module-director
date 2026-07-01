<?php

namespace Icinga\Module\Director\Data;

class PropertiesFilter
{
    public static $CUSTOM_PROPERTY = 'CUSTOM_PROPERTY';
    public static $HOST_PROPERTY = 'HOST_PROPERTY';
    public static $SERVICE_PROPERTY = 'SERVICE_PROPERTY';

    public static $USER_PROPERTY = 'USER_PROPERTY';

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
