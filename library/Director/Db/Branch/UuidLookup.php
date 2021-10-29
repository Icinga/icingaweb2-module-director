<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use function is_int;
use function is_resource;
use function is_string;

class UuidLookup
{
    /**
     * @param Db $connection
     * @param Branch $branch
     * @param string $objectType
     * @param int|string $key
     * @param IcingaHost|null $host
     * @param IcingaServiceSet $set
     */
    public static function findServiceUuid(
        Db $connection,
        Branch $branch,
        $objectType,
        $key = null,
        IcingaHost $host = null,
        IcingaServiceSet $set = null
    ) {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from('icinga_service', 'uuid')->where('object_type = ?', $objectType);
        $query = self::addKeyToQuery($query, $key);
        if ($host) {
            $query->add('host_id = ?', $host->get('id'));
        }
        if ($set) {
            $query->add('service_set_id = ?', $set->get('id'));
        }
        $uuid = self::fetchOptionalUuid($connection, $query);

        if ($uuid === null && $branch->isBranch()) {
            // TODO: use different tables?
            $query = $db->select()->from('branched_icinga_service', 'uuid')->where('object_type = ?', $objectType);
            $query = self::addKeyToQuery($query, $key);
            if ($host) {
                // TODO: uuid?
                $query->add('host = ?', $host->getObjectName());
            }
            if ($set) {
                $query->add('service_set = ?', $set->getObjectName());
            }
            $uuid = self::fetchOptionalUuid($connection, $query);
        }

        return $uuid;
    }

    public static function findUuidForKey($key, $table, Db $connection, Branch $branch)
    {
        $db = $connection->getDbAdapter();
        $query = self::addKeyToQuery($db->select()->from($table, 'uuid'), $key);
        $uuid = self::fetchOptionalUuid($connection, $query);
        if ($uuid === null && $branch->isBranch()) {
            $query = $db->select()->from("branched_$table", 'uuid')->where('object_name = ?', $key);
            $uuid = self::fetchOptionalUuid($connection, $query);
        }

        return $uuid;
    }

    protected static function addKeyToQuery($query, $key)
    {
        if (is_int($key)) {
            $query->where('id = ?', $key);
        } elseif (is_string($key)) {
            $query->where('object_name = ?', $key);
        } else {
            throw new RuntimeException('Cannot deal with non-int/string keys for UUID fallback');
        }

        return $query;
    }

    protected static function fetchOptionalUuid(Db $connection, $query)
    {
        $result = $connection->getDbAdapter()->fetchOne($query);
        if (is_resource($result)) {
            $result = stream_get_contents($result);
        }
        if (is_string($result)) {
            return Uuid::fromBytes($result);
        }

        return null;
    }
}
