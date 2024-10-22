<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Objects\SyncRule;

class BranchSupport
{
    const BRANCHED_TABLE_PREFIX = 'branched_';

    const TABLE_ICINGA_APIUSER            = 'icinga_apiuser';
    const TABLE_ICINGA_COMMAND            = 'icinga_command';
    const TABLE_ICINGA_DEPENDENCY         = 'icinga_dependency';
    const TABLE_ICINGA_ENDPOINT           = 'icinga_endpoint';
    const TABLE_ICINGA_HOST               = 'icinga_host';
    const TABLE_ICINGA_HOSTGROUP          = 'icinga_hostgroup';
    const TABLE_ICINGA_NOTIFICATION       = 'icinga_notification';
    const TABLE_ICINGA_SCHEDULED_DOWNTIME = 'icinga_scheduled_downtime';
    const TABLE_ICINGA_SERVICE            = 'icinga_service';
    const TABLE_ICINGA_SERVICEGROUP       = 'icinga_servicegroup';
    const TABLE_ICINGA_SERVICE_SET        = 'icinga_service_set';
    const TABLE_ICINGA_TIMEPERIOD         = 'icinga_timeperiod';
    const TABLE_ICINGA_USER               = 'icinga_user';
    const TABLE_ICINGA_USERGROUP          = 'icinga_usergroup';
    const TABLE_ICINGA_ZONE               = 'icinga_zone';

    const BRANCHED_TABLE_ICINGA_APIUSER            = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_APIUSER;
    const BRANCHED_TABLE_ICINGA_COMMAND            = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_COMMAND;
    const BRANCHED_TABLE_ICINGA_DEPENDENCY         = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_DEPENDENCY;
    const BRANCHED_TABLE_ICINGA_ENDPOINT           = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_ENDPOINT;
    const BRANCHED_TABLE_ICINGA_HOST               = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_HOST;
    const BRANCHED_TABLE_ICINGA_HOSTGROUP          = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_HOSTGROUP;
    const BRANCHED_TABLE_ICINGA_NOTIFICATION       = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_NOTIFICATION;
    const BRANCHED_TABLE_ICINGA_SCHEDULED_DOWNTIME = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SCHEDULED_DOWNTIME;
    const BRANCHED_TABLE_ICINGA_SERVICE            = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICE;
    const BRANCHED_TABLE_ICINGA_SERVICEGROUP       = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICEGROUP;
    const BRANCHED_TABLE_ICINGA_SERVICE_SET        = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICE_SET;
    const BRANCHED_TABLE_ICINGA_TIMEPERIOD         = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_TIMEPERIOD;
    const BRANCHED_TABLE_ICINGA_USER               = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_USER;
    const BRANCHED_TABLE_ICINGA_USERGROUP          = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_USERGROUP;
    const BRANCHED_TABLE_ICINGA_ZONE               = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_ZONE;

    const OBJECT_TABLES = [
        self::TABLE_ICINGA_APIUSER,
        self::TABLE_ICINGA_COMMAND,
        self::TABLE_ICINGA_DEPENDENCY,
        self::TABLE_ICINGA_ENDPOINT,
        self::TABLE_ICINGA_HOST,
        self::TABLE_ICINGA_HOSTGROUP,
        self::TABLE_ICINGA_NOTIFICATION,
        self::TABLE_ICINGA_SCHEDULED_DOWNTIME,
        self::TABLE_ICINGA_SERVICE,
        self::TABLE_ICINGA_SERVICEGROUP,
        self::TABLE_ICINGA_SERVICE_SET,
        self::TABLE_ICINGA_TIMEPERIOD,
        self::TABLE_ICINGA_USER,
        self::TABLE_ICINGA_USERGROUP,
        self::TABLE_ICINGA_ZONE,
    ];

    const BRANCHED_TABLES = [
        self::BRANCHED_TABLE_ICINGA_APIUSER,
        self::BRANCHED_TABLE_ICINGA_COMMAND,
        self::BRANCHED_TABLE_ICINGA_DEPENDENCY,
        self::BRANCHED_TABLE_ICINGA_ENDPOINT,
        self::BRANCHED_TABLE_ICINGA_HOST,
        self::BRANCHED_TABLE_ICINGA_HOSTGROUP,
        self::BRANCHED_TABLE_ICINGA_NOTIFICATION,
        self::BRANCHED_TABLE_ICINGA_SCHEDULED_DOWNTIME,
        self::BRANCHED_TABLE_ICINGA_SERVICE,
        self::BRANCHED_TABLE_ICINGA_SERVICEGROUP,
        self::BRANCHED_TABLE_ICINGA_SERVICE_SET,
        self::BRANCHED_TABLE_ICINGA_TIMEPERIOD,
        self::BRANCHED_TABLE_ICINGA_USER,
        self::BRANCHED_TABLE_ICINGA_USERGROUP,
        self::BRANCHED_TABLE_ICINGA_ZONE,
    ];

    public static function existsForTableName($table)
    {
        return in_array($table, self::OBJECT_TABLES, true);
    }

    public static function existsForSyncRule(SyncRule $rule)
    {
        return static::existsForTableName(
            DbObjectTypeRegistry::tableNameByType($rule->get('object_type'))
        );
    }
}
