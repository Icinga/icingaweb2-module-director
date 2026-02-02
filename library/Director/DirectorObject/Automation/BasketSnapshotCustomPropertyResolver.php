<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class BasketSnapshotCustomPropertyResolver
{
    /** @var BasketSnapshot */
    protected $snapshot;

    /** @var DbConnection */
    protected $targetDb;

    /** @var array|null */
    protected $requiredUuids;

    /** @var array all BasketSnapshot objects */
    protected $objects;

    /** @var array|null */
    protected $uuidMap;

    /** @var DirectorProperty[]|null */
    protected $targetProperties;

    public function __construct($objects, Db $targetDb)
    {
        $this->objects = $objects;
        $this->targetDb = $targetDb;
    }

    /**
     * @param Db $db
     *
     * @return DirectorProperty[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function loadCurrentProperties(Db $db): array
    {
        $properties = [];
        foreach ($this->getRequiredUuids() as $uuid) {
            $properties[$uuid] = DirectorProperty::loadWithUniqueId(Uuid::fromString($uuid), $db);
        }

        return $properties;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function storeNewProperties()
    {
        $this->targetProperties = null; // Clear Cache
        foreach ($this->getTargetProperties() as $uuid => $property) {
            if ($property->hasBeenModified()) {
                $property->store();
                $this->uuidMap[$uuid] = Uuid::fromBytes($property->get('uuid'))->toString();
            }

            $modified = $this->restoreCustomPropertyItems($property);
            if ($modified && ! isset($this->uuidMap[$uuid])) {
                $this->uuidMap[$uuid] = Uuid::fromBytes($property->get('uuid'))->toString();
            }
        }
    }

    /**
     * @param IcingaObject $new
     * @param $object
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Adapter_Exception
     */
    public function relinkObjectCustomProperties(IcingaObject $new, $object)
    {
        if (! $new->supportsCustomProperties() || ! isset($object->properties)) {
            return;
        }

        $customPropertyMap = $this->getUuidMap();
        $db = $this->targetDb->getDbAdapter();
        $objectUuid = DbUtil::quoteBinaryLegacy($new->get('uuid'), $db);
        $type = $new->getShortTableName();

        $table = $new->getTableName() . '_property';
        $objectKey = $type . '_uuid';
        $existingCustomProperties = [];
        foreach (
            $db->fetchAll(
                $db->select()->from($table)->where("$objectKey = ?", $objectUuid)
            ) as $mapping
        ) {
            $existingCustomProperties[Uuid::fromBytes($mapping->property_uuid)->toString()] = $mapping;
        }

        foreach ($object->properties as $property) {
            $propertyUuid = $property->property_uuid;
            if (! isset($customPropertyMap[$propertyUuid])) {
                throw new InvalidArgumentException(
                    'Basket Snapshot contains invalid custom property reference: ' . $propertyUuid
                );
            }

            $uuid = $customPropertyMap[$propertyUuid];

            if (isset($existingCustomProperties[$uuid])) {
                unset($existingCustomProperties[$uuid]);
            } else {
                $db->insert($table, [
                    $objectKey      => $new->get('uuid'),
                    'property_uuid' => Uuid::fromString($uuid)->getBytes(),
                ]);
            }
        }

        $existingCustomPropertyUuids = array_keys($existingCustomProperties);
        foreach ($existingCustomPropertyUuids as $idx => $uuid) {
            $existingCustomPropertyUuids[$idx] = DbUtil::quoteBinaryLegacy($uuid, $db);
        }

        if (! empty($existingCustomProperties)) {
            $db->delete(
                $table,
                $db->quoteInto(
                    "$objectKey = $objectUuid AND property_uuid IN (?)",
                    $existingCustomPropertyUuids
                )
            );
        }
    }

    /**
     * For diff purposes only, gives '(UNKNOWN)' for custom properties missing
     * in our DB
     *
     * @param object $object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function tweakTargetUuids($object)
    {
        $forward = $this->getUuidMap();
        $map = array_flip($forward);
        if (isset($object->properties)) {
            foreach ($object->properties as $property) {
                $uuid = $property->property_uuid;
                if (isset($map[$uuid])) {
                    $property->property_uuid = $map[$uuid];
                } else {
                    $property->property_uuid = "(UNKNOWN)";
                }
            }
        }
    }

    protected function getRequiredUuids(): array
    {
        if ($this->requiredUuids !== null) {
            return $this->requiredUuids;
        }

        if (isset($this->objects['Property'])) {
            $this->requiredUuids = array_keys($this->objects['Property']);

            return $this->requiredUuids;
        }

        $uuids = [];
        foreach ($this->objects as $objects) {
            foreach ($objects as $object) {
                if (isset($object->properties)) {
                    foreach ($object->properties as $property) {
                        $uuids[$property->property_uuid] = true;
                    }
                }
            }
        }

        $this->requiredUuids = array_keys($uuids);

        return $this->requiredUuids;
    }

    /**
     * @param $type
     * @return object[]
     */
    protected function getObjectsByType($type): array
    {
        if (isset($this->objects->$type)) {
            return (array) $this->objects->$type;
        } else {
            return [];
        }
    }

    /**
     * @return DirectorProperty[]
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getTargetProperties(): array
    {
        if ($this->targetProperties === null) {
            $this->calculateUuidMap();
        }

        return $this->targetProperties;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getUuidMap(): array
    {
        if ($this->uuidMap === null) {
            $this->calculateUuidMap();
        }

        return $this->uuidMap;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function calculateUuidMap()
    {
        $this->uuidMap = [];
        $this->targetProperties = [];
        foreach ($this->getObjectsByType('Property') as $uuid => $object) {
            // Hint: import() doesn't store!
            $new = DirectorProperty::import($object, $this->targetDb);
            if ($new->hasBeenLoadedFromDb()) {
                $newUuid = Uuid::fromBytes($new->get('uuid'))->toString();
            } else {
                $newUuid = Uuid::uuid4()->toString();
            }

            $this->uuidMap[$uuid] = $newUuid;
            $this->targetProperties[$uuid] = $new;
        }
    }

    private function restoreCustomPropertyItems(DirectorProperty $property): bool
    {
        $modified = false;
        foreach ($property->getItems() as $item) {
            if ($item->hasBeenModified()) {
                $item->store();
                $modified = true;
            }

            $modified = $modified || $this->restoreCustomPropertyItems($item);
        }

        return $modified;
    }
}
