<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use gipfl\Json\JsonString;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class BasketDiff
{
    /** @var Db */
    protected $db;
    /** @var ObjectImporter */
    protected $importer;
    /** @var Exporter */
    protected $exporter;
    /** @var BasketSnapshot */
    protected $snapshot;
    /** @var ?stdClass */
    protected $objects = null;
    /** @var BasketSnapshotFieldResolver */
    protected $fieldResolver;

    /** @var BasketSnapshotCustomPropertyResolver */
    protected $customPropertyResolver;

    public function __construct(BasketSnapshot $snapshot, Db $db)
    {
        $this->db = $db;
        $this->importer = new ObjectImporter($db);
        $this->exporter = new Exporter($db);
        $this->snapshot = $snapshot;
    }

    public function hasChangedFor(string $type, string $key, ?UuidInterface $uuid = null): bool
    {
        return $this->getCurrentString($type, $key, $uuid) !== $this->getBasketString($type, $key);
    }

    public function getCurrentString(string $type, string $key, ?UuidInterface $uuid = null): string
    {
        $current = $this->getCurrent($type, $key, $uuid);
        return $current ? JsonString::encode($current, JSON_PRETTY_PRINT) : '';
    }

    public function getBasketString(string $type, string $key): string
    {
        return JsonString::encode($this->getBasket($type, $key), JSON_PRETTY_PRINT);
    }

    protected function getFieldResolver(): BasketSnapshotFieldResolver
    {
        if ($this->fieldResolver === null) {
            $this->fieldResolver = new BasketSnapshotFieldResolver($this->getBasketObjects(), $this->db);
        }

        return $this->fieldResolver;
    }

    protected function getCustomPropertyResolver(): BasketSnapshotCustomPropertyResolver
    {
        if ($this->customPropertyResolver === null) {
            $this->customPropertyResolver = new BasketSnapshotCustomPropertyResolver(
                $this->getBasketObjects(),
                $this->db
            );
        }

        return $this->customPropertyResolver;
    }

    protected function getCurrent(string $type, string $key, ?UuidInterface $uuid = null): ?stdClass
    {
        if ($uuid && $current = BasketSnapshot::instanceByUuid($type, $uuid, $this->db)) {
            $exported = $this->exporter->export($current);
            $this->getFieldResolver()->tweakTargetIds($exported);
            $this->getCustomPropertyResolver()->tweakTargetUuids($exported);
        } elseif ($current = BasketSnapshot::instanceByIdentifier($type, $key, $this->db)) {
            $exported = $this->exporter->export($current);
            $this->getFieldResolver()->tweakTargetIds($exported);
            $this->getCustomPropertyResolver()->tweakTargetUuids($exported);
        } else {
            $exported = null;
        }
        CompareBasketObject::normalize($exported);

        return $exported;
    }

    protected function getBasket($type, $key): stdClass
    {
        $object = $this->getBasketObject($type, $key);
        $fields = $object->fields ?? null;
        $properties = $object->properties ?? null;
        $reExport = $this->exporter->export(
            $this->importer->import(BasketSnapshot::getClassForType($type), $object)
        );
        if ($fields === null) {
            unset($reExport->fields);
        } else {
            $reExport->fields = $fields;
        }

        if ($properties === null) {
            unset($reExport->properties);
        } else {
            $reExport->properties = $properties;
        }

        CompareBasketObject::normalize($reExport);

        return $reExport;
    }

    public function hasCurrentInstance(string $type, string $key, ?UuidInterface $uuid = null): bool
    {
        return $this->getCurrentInstance($type, $key, $uuid) !== null;
    }

    public function getCurrentInstance(string $type, string $key, ?UuidInterface $uuid = null)
    {
        if ($uuid && $instance = BasketSnapshot::instanceByUuid($type, $uuid, $this->db)) {
            return $instance;
        } else {
            return BasketSnapshot::instanceByIdentifier($type, $key, $this->db);
        }
    }

    public function getBasketObjects(): stdClass
    {
        if ($this->objects === null) {
            $this->objects = JsonString::decode($this->snapshot->getJsonDump());
        }

        return $this->objects;
    }

    public function getBasketObject(string $type, string $key): stdClass
    {
        return $this->getBasketObjects()->$type->$key;
    }
}
