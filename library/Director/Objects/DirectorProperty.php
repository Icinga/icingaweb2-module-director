<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\CompareBasketObject;
use Ramsey\Uuid\Uuid;
use stdClass;

class DirectorProperty extends DbObject
{
    protected $table = 'director_property';

    protected $keyName = 'key_name';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'uuid'          => null,
        'key_name'      => null,
        'parent_uuid'   => null,
        'category_id'   => null,
        'value_type'    => null,
        'label'         => null,
        'description'   => null
    ];

    protected $binaryProperties = [
        'uuid',
        'parent_uuid'
    ];

    protected $relations = [
        'category' => 'DirectorDatafieldCategory'
    ];

    /** @var DirectorProperty[] */
    private $items = [];

    /** @var ?DirectorDatalist */
    private $datalist = null;

    /** @var ?DirectorDatafieldCategory */
    private $category;

    protected function setDbProperties($properties)
    {
        unset($properties->parent_uuid_v); // hack to ignore virtual column, need a better solution

        return parent::setDbProperties($properties);
    }

    /**
     * Get category to which the property belongs to
     *
     * @return ?DirectorDatafieldCategory
     *
     * @throws NotFoundError
     */
    public function getCategory(): ?DirectorDatafieldCategory
    {
        if ($this->category) {
            return $this->category;
        }

        if ($id = $this->get('category_id')) {
            $this->category = DirectorDatafieldCategory::loadWithAutoIncId($id, $this->getConnection());

            return $this->category;
        }

        return null;
    }

    /**
     * Get the category name to which the property belongs to
     *
     * @return ?string
     */
    public function getCategoryName(): ?string
    {
        $category = $this->getCategory();
        if ($category === null) {
            return null;
        }

        return $category->get('category_name');
    }

    /**
     * Set the category to which the property belongs to
     *
     * @param DirectorDatafieldCategory|string|null $category
     *
     * @return void
     */
    public function setCategory($category): void
    {
        if ($category === null) {
            $this->category = null;
            $this->set('category_id', null);
        } elseif ($category instanceof DirectorDatafieldCategory) {
            if ($category->hasBeenLoadedFromDb()) {
                $this->set('category_id', $category->get('id'));
            }

            $this->category = $category;
        } else {
            $category = DirectorDatafieldCategory::loadOptional($category, $this->getConnection());
            if ($category) {
                $this->setCategory($category);
            } else {
                $this->setCategory(DirectorDatafieldCategory::create(
                    ['category_name' => $category],
                    $this->getConnection()
                ));
            }
        }
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
            $plain->items = $this->exportChildren();

            if (str_starts_with($plain->value_type, 'datalist-')) {
                $query = $this->db->select()->from(['dd' => 'director_datalist'], ['list_name'])
                    ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                    ->where($this->db->quoteInto('dpdl.property_uuid = ?', $uuid->getBytes()));
                $plain->datalist = $this->db->fetchOne($query);
            }
        }

        if ($plain->parent_uuid !== null) {
            $plain->parent_uuid = Uuid::fromBytes($plain->parent_uuid)->toString();
        }

        if (property_exists($plain, 'category_id')) {
            $plain->category = $this->getCategoryName();
            unset($plain->category_id);
        }

        return $plain;
    }

    /**
     * Export the child properties of this director property.
     *
     * @return array
     */
    private function exportChildren(): array
    {
        $properties = [];
        foreach ($this->fetchItemsFromDb() as $property) {
            $properties[$property->get('key_name')] = $property->export();
        }

        return $properties;
    }

    /**
     * Get the child properties of this director property.
     *
     * @return DirectorProperty[]
     */
    public function fetchItemsFromDb(): array
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
            foreach ($item->fetchItemsFromDb() as $nestedItem) {
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
        $datalist = null;
        // DirectorProperty items (children)
        $items = $plain->items ?? [];
        unset($plain->items);

        // If DirectorProperty has a UUID, load it from the database using the "uuid" property
        if ($uuid) {
            $uuid = Uuid::fromString($uuid);
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

        // If DirectorProperty has no UUID (mainly for property children),
        // load it from the database using the "key_name" property
        $query = $dba->select()->from('director_property')->where('key_name = ?', $plain->key_name);
        if (isset($plain->parent_uuid)) {
            $query->where('parent_uuid = ?', $plain->parent_uuid);
        } else {
            $query->where('parent_uuid is NULL');
        }

        $dbRow = $dba->fetchRow($query);
        if ($dbRow !== false) {
            $candidate = DirectorProperty::fromDbRow($dbRow, $db);
            $export = $candidate->export();
            if (isset($export->parent_uuid)) {
                $export->parent = DirectorProperty::loadWithUniqueId(Uuid::fromString($export->parent_uuid), $db)
                    ->get('key_name');
                unset($export->parent_uuid);
            }

            CompareBasketObject::normalize($export);
            $plainParentUuid = $plain->parent_uuid ?? null;
            if (isset($plain->parent_uuid)) {
                $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($plain->parent_uuid), $db);
                if ($parent === null) {
                    unset($plain->parent);
                    $plain->parent_uuid = $plainParentUuid;
                } else {
                    $plain->parent = $parent->get('key_name');
                    unset($plain->parent_uuid);
                }
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

    protected function onStore(): void
    {
        if ($this->getDatalist()) {
            $this->db->insert(
                'director_property_datalist',
                ['property_uuid' => $this->get('uuid'), 'list_uuid' => $this->datalist->get('uuid')]
            );
        }
    }

    /**
     * Import the children of the director property recursively from the given array of imported
     * items in the plain object.
     *
     * @param array $items
     * @param Db    $db
     *
     * @return array
     */
    private function importItems(array $items, Db $db): array
    {
        if (empty($items)) {
            return [];
        }

        $itemCandidates = [];
        foreach ($items as $key => $value) {
            $itemUUid = $value->uuid ?? null;
            $nestedItems = (array) ($value->items ?? []);
            unset($value->items);
            if ($itemUUid === null) {
                continue;
            }

            $itemUUid = Uuid::fromString($itemUUid);
            $itemCandidate = DirectorProperty::loadWithUniqueId($itemUUid, $db);
            if (! $itemCandidate) {
                if (isset($value->parent_uuid)) {
                    $value->parent_uuid = Uuid::fromString($value->parent_uuid)->getBytes();
                }

                $itemCandidates[$key] = DirectorProperty::import($value, $db);

                continue;
            }

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

            if ($datalist) {
                $itemCandidate->datalist = $datalist;
            }

            if ($nestedItems) {
                $itemCandidate->items = $this->importItems($nestedItems, $db);
            }

            $itemCandidates[$key] = $itemCandidate;
        }

        return $itemCandidates;
    }
}
