<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use function is_int;
use function is_resource;
use function is_string;

class UuidLookup
{
    /**
     * @param int|string $key
     * @return ?UuidInterface
     */
    public static function findServiceUuid(
        Db $connection,
        Branch $branch,
        ?string $objectType = null,
        $key = null,
        IcingaHost $host = null,
        IcingaServiceSet $set = null
    ) {
        $db = $connection->getDbAdapter();
        $query = $db->select()->from('icinga_service', 'uuid');
        if ($objectType) {
            $query->where('object_type = ?', $objectType);
        }
        $query = self::addKeyToQuery($connection, $query, $key);
        if ($set) {
            $setId = $set->get('id');
            if ($setId === null) {
                $query->where('1 = 0');
            } else {
                $query->where('service_set_id = ?', $setId);
            }
        } elseif ($host) {
            $hostId = $host->get('id');
            if ($hostId === null) {
                $query->where('1 = 0');
            } else {
                $query->where('host_id = ?', $hostId);
            }
        }
        $uuid = self::fetchOptionalUuid($connection, $query);

        if ($uuid === null && $branch->isBranch()) {
            // TODO: use different tables?
            $query = $db->select()
                ->from('branched_icinga_service', 'uuid')
                ->where('branch_uuid = ?', $connection->quoteBinary($branch->getUuid()->getBytes()));
            if ($objectType) {
                $query->where('object_type = ?', $objectType);
            }
            $query = self::addKeyToQuery($connection, $query, $key);
            if ($host) {
                // TODO: uuid?
                $query->where('host = ?', $host->getObjectName());
            }
            if ($set) {
                $query->where('service_set = ?', $set->getObjectName());
            }

            $uuid = self::fetchOptionalUuid($connection, $query);
        }

        return $uuid;
    }

    /**
     * @param int|string|array $key
     * @param string $table
     * @param Db $connection
     * @param Branch $branch
     * @return UuidInterface
     * @throws NotFoundError
     */
    public static function requireUuidForKey($key, $table, Db $connection, Branch $branch)
    {
        $uuid = self::findUuidForKey($key, $table, $connection, $branch);
        if ($uuid === null) {
            throw new NotFoundError('No such object available');
        }

        return $uuid;
    }

    /**
     * @param int|string|array $key
     * @param string $table
     * @param Db $connection
     * @param Branch $branch
     * @return ?UuidInterface
     */
    public static function findUuidForKey($key, $table, Db $connection, Branch $branch)
    {
        $db = $connection->getDbAdapter();
        $query = self::addKeyToQuery($connection, $db->select()->from($table, 'uuid'), $key);
        $uuid = self::fetchOptionalUuid($connection, $query);
        if ($uuid === null && $branch->isBranch()) {
            if (is_array($key) && isset($key['host_id'])) {
                $key['host'] = IcingaHost::loadWithAutoIncId((int) $key['host_id'], $connection)->getObjectName();
                unset($key['host_id']);
            }
            $query = self::addKeyToQuery($connection, $db->select()->from("branched_$table", 'uuid'), $key);
            $query->where('branch_uuid = ?', $connection->quoteBinary($branch->getUuid()->getBytes()));
            $uuid = self::fetchOptionalUuid($connection, $query);
        }

        return $uuid;
    }

    protected static function addKeyToQuery(Db $connection, $query, $key)
    {
        if (is_int($key)) {
            $query->where('id = ?', $key);
        } elseif (is_string($key)) {
            $query->where('object_name = ?', $key);
        } else {
            foreach ($key as $k => $v) {
                $query->where($connection->getDbAdapter()->quoteIdentifier($k) . ' = ?', $v);
            }
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
