<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaHost extends DbObject
{
    protected $table = 'icinga_host';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'address'               => null,
        'address6'              => null,
        'check_command_id'      => null,
        'max_check_attempts'    => null,
        'check_period_id'       => null,
        'check_interval'        => null,
        'retry_interval'        => null,
        'enable_notifications'  => null,
        'enable_active_checks'  => null,
        'enable_passive_checks' => null,
        'enable_event_handler'  => null,
        'enable_flapping'       => null,
        'enable_perfdata'       => null,
        'event_command_id'      => null,
        'flapping_threshold'    => null,
        'volatile'              => null,
        'zone_id'               => null,
        'command_endpoint_id'   => null,
        'notes'                 => null,
        'notes_url'             => null,
        'action_url'            => null,
        'icon_image'            => null,
        'icon_image_alt'        => null,
        'object_type'           => null,
    );
}
