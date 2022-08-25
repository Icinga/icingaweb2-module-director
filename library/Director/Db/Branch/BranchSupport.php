<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Objects\SyncRule;

class BranchSupport
{
    const BRANCHED_TABLES = [
        'icinga_apiuser',
        'icinga_command',
        'icinga_dependency',
        'icinga_endpoint',
        'icinga_host',
        'icinga_hostgroup',
        'icinga_notification',
        'icinga_scheduled_downtime',
        'icinga_service',
        'icinga_servicegroup',
        'icinga_timeperiod',
        'icinga_user',
        'icinga_usergroup',
        'icinga_zone',
    ];

    public static function existsForTableName($table)
    {
        return in_array($table, self::BRANCHED_TABLES, true);
    }

    public static function existsForSyncRule(SyncRule $rule)
    {
        return static::existsForTableName(
            DbObjectTypeRegistry::tableNameByType($rule->get('object_type'))
        );
    }
}
