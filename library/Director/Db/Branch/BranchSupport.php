<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Objects\SyncRule;

class BranchSupport
{
    public const BRANCHED_TABLE_PREFIX = 'branched_';

    public const TABLE_ICINGA_APIUSER = 'icinga_apiuser';
    public const TABLE_ICINGA_COMMAND = 'icinga_command';
    public const TABLE_ICINGA_DEPENDENCY = 'icinga_dependency';
    public const TABLE_ICINGA_ENDPOINT = 'icinga_endpoint';
    public const TABLE_ICINGA_HOST = 'icinga_host';
    public const TABLE_ICINGA_HOSTGROUP = 'icinga_hostgroup';
    public const TABLE_ICINGA_NOTIFICATION = 'icinga_notification';
    public const TABLE_ICINGA_SCHEDULED_DOWNTIME = 'icinga_scheduled_downtime';
    public const TABLE_ICINGA_SERVICE = 'icinga_service';
    public const TABLE_ICINGA_SERVICEGROUP = 'icinga_servicegroup';
    public const TABLE_ICINGA_SERVICE_SET = 'icinga_service_set';
    public const TABLE_ICINGA_TIMEPERIOD = 'icinga_timeperiod';
    public const TABLE_ICINGA_USER = 'icinga_user';
    public const TABLE_ICINGA_USERGROUP = 'icinga_usergroup';
    public const TABLE_ICINGA_ZONE = 'icinga_zone';

    public const BRANCHED_TABLE_ICINGA_APIUSER = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_APIUSER;
    public const BRANCHED_TABLE_ICINGA_COMMAND = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_COMMAND;
    public const BRANCHED_TABLE_ICINGA_DEPENDENCY = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_DEPENDENCY;
    public const BRANCHED_TABLE_ICINGA_ENDPOINT = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_ENDPOINT;
    public const BRANCHED_TABLE_ICINGA_HOST = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_HOST;
    public const BRANCHED_TABLE_ICINGA_HOSTGROUP = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_HOSTGROUP;
    public const BRANCHED_TABLE_ICINGA_NOTIFICATION = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_NOTIFICATION;
    public const BRANCHED_TABLE_ICINGA_SCHEDULED_DOWNTIME =
        self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SCHEDULED_DOWNTIME;
    public const BRANCHED_TABLE_ICINGA_SERVICE = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICE;
    public const BRANCHED_TABLE_ICINGA_SERVICEGROUP = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICEGROUP;
    public const BRANCHED_TABLE_ICINGA_SERVICE_SET = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_SERVICE_SET;
    public const BRANCHED_TABLE_ICINGA_TIMEPERIOD = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_TIMEPERIOD;
    public const BRANCHED_TABLE_ICINGA_USER = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_USER;
    public const BRANCHED_TABLE_ICINGA_USERGROUP = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_USERGROUP;
    public const BRANCHED_TABLE_ICINGA_ZONE = self::BRANCHED_TABLE_PREFIX . self::TABLE_ICINGA_ZONE;

    public const OBJECT_TABLES = [
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

    public const BRANCHED_TABLES = [
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
