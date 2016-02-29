<?php

namespace Icinga\Module\Director\Objects;

class IcingaNotification extends IcingaObject
{
    protected $table = 'icinga_notification';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'host_id'               => null,
        'service_id'            => null,
        // 'users'                 => null,
        // 'user_groups'           => null,
        'times_begin'           => null,
        'times_end'             => null,
        'command_id'            => null,
        'notification_interval' => null,
        'period_id'             => null,
        'zone_id'               => null,
    );

    protected $supportsCustomVars = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $relatedSets = array(
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    );

    protected $relations = array(
        'zone'    => 'IcingaZone',
        'host'    => 'IcingaHost',
        'command' => 'IcingaCommand',
        'period'  => 'IcingaTimePeriod',
        'service' => 'IcingaService',
    );

    // listOfRelations -> users, user_groups
}
