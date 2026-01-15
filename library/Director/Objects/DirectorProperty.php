<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\CompareBasketObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class DirectorProperty extends DbObject
{
    protected $table = 'director_property';

    protected $keyName = 'id';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'uuid'          => null,
        'key_name'      => null,
        'parent_uuid'   => null,
        'value_type'    => null,
        'label'         => null,
        'description'   => null
    ];

    /** @var DirectorProperty[] */
    private $items = [];

    private $object;

    protected function setDbProperties($properties)
    {
        unset($properties->parent_uuid_v); // hack to ignore virtual column, need a better solution

        return parent::setDbProperties($properties);
    }

    /**
     * @throws NotFoundError
     */
    public function export(): stdClass
    {
        $plain = (object) $this->getProperties();
        $uuid = $this->get('uuid');
        $parentUuid = $this->get('parent_uuid');
        if ($uuid) {
            $uuid = Uuid::fromBytes($uuid);
            unset($plain->parent_uuid_v); // A virtual added for composite unique constraint
            $plain->uuid = $uuid->toString();
            $plain->items = $this->fetchChildren();
        }

        if ($parentUuid) {
            $plain->parent_uuid = Uuid::fromBytes($parentUuid)->toString();
        }

        return $plain;
    }

    private function fetchChildren(): array
    {
        $properties = [];
        foreach ($this->getItems() as $property) {
            $properties[$property->get('key_name')] = $property->export();
        }

        return $properties;
    }

    public function getItems(): array
    {
        if ($this->items) {
            return $this->items;
        }

        $uuid = $this->get('uuid');
        if ($uuid === null) {
            return [];
        }

        $uuid = Uuid::fromBytes($uuid);
        $query = $this->db->select()
                          ->from('director_property')
                          ->where(
                              'parent_uuid = ?',
                              Db\DbUtil::quoteBinaryLegacy($uuid->getBytes(), $this->db)
                          );

        foreach (DirectorProperty::loadAll($this->connection, $query) as $item) {
            $item->items = $item->getItems();
            $this->items[] = $item;
        }

        return $this->items;
    }

    public static function fromDbRow($row, Db $connection)
    {
        $obj = static::create((array) $row, $connection);
        $obj->loadedFromDb = true;
        $obj->hasBeenModified = false;
        $obj->modifiedProperties = [];
        $obj->onLoadFromDb();

        return $obj;
    }


    /**
     * @throws NotFoundError
     */
    public static function import(stdClass $plain, Db $db): static
    {
        $dba = $db->getDbAdapter();
        $uuid = $plain->uuid ?? null;
        if ($uuid) {
            $uuid = Uuid::fromString($uuid);
            $items = $plain->items ?? [];
            unset($plain->items);
            $candidate = DirectorProperty::loadWithUniqueId($uuid, $db);
            if ($candidate) {
                assert($candidate instanceof DirectorProperty);
                $candidate->setProperties((array) $plain);
                $candidate->items = $candidate->importItems((array) $items, $db);

                return $candidate;
            }
        }

        $query = $dba->select()->from('director_property')->where('key_name = ?', $plain->key_name);
        $candidates = DirectorProperty::loadAll($db, $query);
        foreach ($candidates as $candidate) {
            $export = $candidate->export();
            CompareBasketObject::normalize($export);
            unset($export->uuid);
            if (CompareBasketObject::equals($export, $plain)) {
                return $candidate;
            }
        }

        return static::create((array) $plain, $db);
    }

    private function importItems(array $items, Db $db): array
    {
        $itemCandidates = [];
        foreach ($items as $key => $value) {
            $itemUUid = $value->uuid ?? null;
            if ($itemUUid) {
                $itemUUid = Uuid::fromString($itemUUid);
                $nestedItems = $value->items ?? [];
                unset($value->items);
                $itemCandidate = DirectorProperty::loadWithUniqueId($itemUUid, $db);
                if ($itemCandidate) {
                    assert($itemCandidate instanceof DirectorProperty);
                    if (isset($value->parent_uuid)) {
                        $value->parent_uuid = Uuid::fromString($value->parent_uuid)->getBytes();
                    }

                    $itemCandidate->setProperties((array) $value);
                    $itemCandidate->items = $this->importItems($nestedItems, $db);
                    $itemCandidates[$key] = $itemCandidate;
                } else {
                    if (isset($value->parent_uuid)) {
                        $value->parent_uuid = Uuid::fromString($value->parent_uuid)->getBytes();
                    }

                    $itemCandidates[$key] = DirectorProperty::import($value, $db);
                }
            }
        }

        return $itemCandidates;
    }

    protected function setObject(IcingaObject $object)
    {
        $this->object = $object;
    }

    protected function getObject()
    {
        return $this->object;
    }
}
