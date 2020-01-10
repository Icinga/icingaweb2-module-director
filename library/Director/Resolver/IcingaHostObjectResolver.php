<?php

namespace Icinga\Module\Director\Resolver;

use Zend_Db_Adapter_Abstract as ZfDB;

class IcingaHostObjectResolver extends IcingaObjectResolver
{
    /** @var ZfDB */
    protected $db;

    protected $nameMaps;

    protected $baseTable = 'icinga_host';

    protected $ignoredProperties = [
        'id',
        'object_type',
        'disabled',
        'has_agent',
        'master_should_connect',
        'accept_config',
        'api_key',
        'template_choice_id',
    ];

    protected $relatedTables = [
        'check_command_id'    => 'icinga_command',
        'event_command_id'    => 'icinga_command',
        'check_period_id'     => 'icinga_timeperiod',
        'command_endpoint_id' => 'icinga_endpoint',
        'zone_id'             => 'icinga_zone',
    ];

    protected $booleans = [
        'enable_notifications',
        'enable_active_checks',
        'enable_passive_checks',
        'enable_event_handler',
        'enable_flapping',
        'enable_perfdata',
        'volatile',
    ];
}
