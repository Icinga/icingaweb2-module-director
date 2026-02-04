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

    protected $binaryProperties = [
        'parent_uuid'
    ];

    /** @var DirectorProperty[] */
    private $items = [];

    /** @var ?DirectorDatalist */
    private $datalist = null;

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
        if ($uuid) {
            $uuid = Uuid::fromBytes($uuid);
            $plain->uuid = $uuid->toString();
            $plain->items = $this->fetchChildren();

            if (str_starts_with($plain->value_type, 'datalist-')) {
                $query = $this->db->select()->from(['dd' => 'director_datalist'], ['list_name'])
                    ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                    ->where($this->db->quoteInto('dpdl.property_uuid = ?', $uuid->getBytes()));
                $plain->datalist = $this->db->fetchOne($query);
            }

            if ($plain->parent_uuid !== null) {
                $plain->parent_uuid = Uuid::fromBytes($plain->parent_uuid)->toString();
            }
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
            if (isset($item->parent_uuid_v)) {
                unset($item->parent_uuid_v);
            }

            foreach ($item->getItems() as $nestedItem) {
                if (isset($nestedItem->parent_uuid_v)) {
                    unset($nestedItem->parent_uuid_v);
                }

                $item->items[] = $nestedItem;
            }

            $this->items[] = $item;
        }

        return $this->items;
    }

    public function getDatalist(): ?DirectorDatalist
    {
        if ($this->datalist) {
            return $this->datalist;
        }

        if (str_starts_with($this->get('value_type'), 'datalist-')) {
            $query = $this->db->select()->from(['dd' => 'director_datalist'], ['list_name'])
                ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                ->where($this->db->quoteInto('dpdl.property_uuid = ?', $this->get('uuid')));
            $this->datalist = DirectorDatalist::load($this->db->fetchOne($query), $this->connection);
        }

        return $this->datalist;
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
        $items = [];
        if ($uuid) {
            $uuid = Uuid::fromString($uuid);
            $items = $plain->items ?? [];
            unset($plain->items);
            $datalist = null;
            if (isset($plain->datalist)) {
                $datalist = DirectorDatalist::loadOptional($plain->datalist, $db);
                if (! $datalist && is_string($plain->datalist)) {
                    $datalist = DirectorDatalist::create(['list_name' => $plain->datalist], $db);
                }

                unset($plain->datalist);
            }

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

            if (isset($export->parent_uuid)) {
                $export->parent = DirectorProperty::loadWithUniqueId(Uuid::fromString($export->parent_uuid), $db)
                    ->get('key_name');
                unset($export->parent_uuid);
            }

            $plainParentUuid = $plain->parent_uuid ?? null;
            if (isset($plain->parent_uuid)) {
                $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($plain->parent_uuid), $db);
                if ($parent === null) {
                    unset($plain->parent);
                    $plain->parent_uuid = $plainParentUuid;

                    continue;
                }

                $plain->parent = $parent->get('key_name');
                unset($plain->parent_uuid);
            }

            unset($export->uuid);

            if (CompareBasketObject::equals($export, $plain)) {
                return $candidate;
            }

            if ($plainParentUuid !== null) {
                unset($plain->parent);
                $plain->parent_uuid = $plainParentUuid;
            }
        }

        $property = static::create((array) $plain, $db);

        if ($datalist) {
            $property->datalist = $datalist;
        }

        if ($items) {
            $property->items = $property->importItems((array) $items, $db);
        }

        return $property;
    }

    protected function onStore()
    {
        if ($this->getDatalist()) {
            $this->db->insert(
                'director_property_datalist',
                ['property_uuid' => $this->get('uuid'), 'list_uuid' => $this->datalist->get('uuid')]
            );
        }

        parent::onStore();
    }

    private function importItems(array $items, Db $db): array
    {
        if (empty($items)) {
            return [];
        }

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

                    $datalist = null;
                    if (isset($value->datalist)) {
                        $datalist = DirectorDatalist::loadOptional($value->datalist, $db);
                        if (! $datalist && is_string($value->datalist)) {
                            $datalist = DirectorDatalist::create(['list_name' => $value->datalist], $db);
                        }

                        unset($value->datalist);
                    }

                    $itemCandidate->setProperties((array) $value);
                    $itemCandidate->items = $this->importItems((array) $nestedItems, $db);
                    if ($datalist) {
                        $itemCandidate->datalist = $datalist;
                    }

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
}
