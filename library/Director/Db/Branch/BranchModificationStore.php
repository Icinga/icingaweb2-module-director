<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Data\Json;
use Icinga\Module\Director\Db;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class BranchModificationStore
{
    protected $connection;

    protected $db;

    protected $shortType;

    protected $table;

    // TODO: Ranges is weird. key = scheduled_downtime_id, range_type, range_key
    protected $encodedArrays = ['imports', 'groups', 'ranges'];

    protected $encodedDictionaries = ['vars', 'arguments'];

    public function __construct(Db $connection, $shortType)
    {
        $this->connection = $connection;
        $this->shortType = $shortType;
        $this->table = "branched_icinga_$shortType";
        $this->db = $connection->getDbAdapter();
    }

    public function loadAll(UuidInterface $branchUuid)
    {
        return $this->db->fetchAll($this->select()->where('branch_uuid = ?', $branchUuid->getBytes()));
    }

    public function eventuallyLoadModification($objectId, UuidInterface $branchUuid)
    {
        if ($objectId) {
            $row = $this->fetchOptional($objectId, $branchUuid);
        } else {
            return null;
        }
        if ($row) {
            $id = (int) $objectId;
            $class = DbObjectTypeRegistry::classByType($this->shortType);
            if ($row->deleted === 'y') {
                return ObjectModification::delete($class, $id, static::cleanupRow($row));
            }
            if ($row->created === 'y') {
                return ObjectModification::create($class, $row->object_name, static::cleanupRow($row));
            }

            // TODO: Former properties null? DB Problem.
            return ObjectModification::modify($class, $id, null, static::filterNull(static::cleanupRow($row)));
        }

        return null;
    }

    public function loadOptionalModificationByName($objectName, UuidInterface $branchUuid)
    {
        $row = $this->fetchOptionalByName($objectName, $branchUuid);
        if ($row) {
            $class = DbObjectTypeRegistry::classByType($this->shortType);
            if ($row->created === 'y') {
                return ObjectModification::create($class, $row->object_name, static::cleanupRow($row));
            }
            if ($row->deleted === 'y') {
                throw new \RuntimeException('Delete for a probably non-existing object? Not sure');
                // return ObjectModification::delete($class, $row->object_name, ...);
            }

            // Hint: this is not correct. Former properties are missing. We finish up here, when loading renamed objects.
            return ObjectModification::modify($class, $row->object_name, [], static::filterNull(static::cleanupRow($row)));

            // TODO: better exception, handle this in the frontend
            //throw new \RuntimeException('Got a modification for a probably non-existing object');
        }

        return null;
    }

    protected function filterNull($row)
    {
        return (object) array_filter((array) $row, function ($value) {
            return $value !== null;
        });
    }

    protected function cleanupRow($row)
    {
        unset($row->object_id, $row->class, $row->branch_uuid, $row->uuid, $row->created, $row->deleted);
        return $row;
    }

    protected function fetchOptional($objectId, UuidInterface $branchUuid)
    {
        return $this->optionalRow($this->select()
            ->where('object_id = ?', $objectId)
            ->where('branch_uuid = ?', $branchUuid->getBytes()));
    }

    protected function fetchOptionalByName($objectName, UuidInterface $branchUuid)
    {
        return $this->optionalRow($this->select()
            ->where('object_name = ?', $objectName)
            ->where('branch_uuid = ?', $branchUuid->getBytes()));
    }

    protected function optionalRow($query)
    {
        if ($row = $this->db->fetchRow($query)) {
            $this->decodeEncodedProperties($row);
            return $row;
        }

        return null;
    }

    protected function select()
    {
        return $this->db->select()->from($this->table);
    }

    protected function decodeEncodedProperties($row)
    {
        foreach (array_merge($this->encodedArrays, $this->encodedDictionaries) as $encodedProperty) {
            // vars, imports and groups might be null or not set at all (if not supported)
            if (! empty($row->$encodedProperty)) {
                $row->$encodedProperty = Json::decode($row->$encodedProperty);
            }
        }
    }

    protected function prepareModificationForStore(ObjectModification $modification)
    {
        // TODO.
    }

    public function store(ObjectModification $modification, $objectId, UuidInterface $branchUuid)
    {
        if ($properties = $modification->getProperties()) {
            $properties = (array) $properties->jsonSerialize();
        } else {
            $properties = [];
        }
        // Former properties are not needed, as they are dealt with in persistModification.

        if ($objectId) {
            $existing = $this->fetchOptional($objectId, $branchUuid);
            foreach ($this->encodedDictionaries as $property) {
                $this->combineAndEncodeFlatDictionaries($properties, $existing, $property);
            }
        } else {
            $existing = null;
        }
        foreach ($this->encodedArrays as $deepProperty) {
            if (isset($properties[$deepProperty])) {
                // TODO: flags
                $properties[$deepProperty] = Json::encode($properties[$deepProperty]);
            }
        }
        $this->connection->runFailSafeTransaction(function () use (
            $existing,
            $modification,
            $objectId,
            $branchUuid,
            $properties
        ) {
            if ($existing) {
                if ($modification->isDeletion()) {
                    $this->deleteExisting($existing->uuid);
                    $this->delete($objectId, $branchUuid);
                } elseif ($existing->deleted === 'y') {
                    $this->deleteExisting($existing->uuid);
                    $this->create($objectId, $branchUuid, $properties);
                } else {
                    $this->update($existing->uuid, $properties);
                }
            } else {
                if ($modification->isCreation()) {
                    $this->create($objectId, $branchUuid, $properties);
                } elseif ($modification->isDeletion()) {
                    $this->delete($objectId, $branchUuid);
                } else {
                    $this->createModification($objectId, $branchUuid, $properties);
                }
            }
            $activities = new BranchActivityStore($this->connection);
            $activities->persistModification($modification, $branchUuid);
        });
    }

    protected function combineAndEncodeFlatDictionaries(&$properties, $existing, $prefix)
    {
        if ($existing && ! empty($existing->$prefix)) {
            // $vars = (array) Json::decode($existing->vars);
            $vars = (array) ($existing->$prefix);
        } else {
            $vars = [];
        }
        $length = strlen($prefix) + 1;
        foreach ($properties as $key => $value) {
            if (substr($key, 0, $length) === "$prefix.") {
                $vars[substr($key, $length)] = $value;
            }
        }
        if (! empty($vars)) {
            foreach (array_keys($vars) as $key) {
                unset($properties["$prefix.$key"]);
            }
            $properties[$prefix] = Json::encode((object) $vars); // TODO: flags!
        }
    }

    protected function deleteExisting($binaryUuid)
    {
        $this->db->delete($this->table, $this->db->quoteInto('uuid = ?', $binaryUuid));
    }

    protected function create($objectId, UuidInterface $branchUuid, $properties)
    {
        $this->db->insert($this->table, [
            'branch_uuid' => $branchUuid->getBytes(),
            'uuid'        => Uuid::uuid4()->getBytes(),
            'object_id'   => $objectId,
            'created'     => 'y',
        ] + $properties);
    }

    protected function delete($objectId, UuidInterface $branchUuid)
    {
        $this->db->insert($this->table, [
            'branch_uuid' => $branchUuid->getBytes(),
            'uuid'        => Uuid::uuid4()->getBytes(),
            'object_id'   => $objectId,
            'deleted'     => 'y',
        ]);
    }

    protected function createModification($objectId, UuidInterface $branchUuid, $properties)
    {
        $this->db->insert($this->table, [
            'branch_uuid' => $branchUuid->getBytes(),
            'uuid'        => Uuid::uuid4()->getBytes(),
            'object_id'   => $objectId,
        ] + $properties);
    }

    protected function update($binaryUuid, $properties)
    {
        $this->db->update($this->table, [
            'uuid' => Uuid::uuid4()->getBytes(),
        ] + $properties, $this->db->quoteInto('uuid = ?', $binaryUuid));
    }
}
