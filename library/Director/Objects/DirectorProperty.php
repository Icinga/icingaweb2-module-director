<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\CompareBasketObject;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use stdClass;

class DirectorProperty extends DbObject
{
    /** Value types that may never be used for a nested (non-top-level) property */
    private const NON_NESTABLE_TYPES = ['dynamic-dictionary'];

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
        if (! is_array($properties)) {
            $properties = (array) $properties;
        }

        return parent::setDbProperties($this->stripVirtualParentUuidColumn($properties));
    }

    public function setProperties($props)
    {
        return parent::setProperties($this->stripVirtualParentUuidColumn($props));
    }

    /**
     * MySQL exposes parent_uuid_v as a virtual/generated column; strip it before it reaches
     * the parent's property handling, which knows nothing about it. Needs a better solution.
     *
     * @param array $properties
     *
     * @return array
     */
    private function stripVirtualParentUuidColumn(array $properties): array
    {
        $connection = $this->getConnection();
        if ($connection && $connection->isMysql() && isset($properties['parent_uuid_v'])) {
            unset($properties['parent_uuid_v']);
        }

        return $properties;
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
        $db = $this->getDb();
        $uuid = Db\DbUtil::binaryResult($this->get('uuid'));
        if ($uuid) {
            $uuid = Uuid::fromBytes($uuid);
            $plain->uuid = $uuid->toString();
            $plain->items = $this->exportChildren();

            if (str_starts_with($plain->value_type, 'datalist-')) {
                $query = $this->db->select()->from(['dd' => 'director_datalist'], ['list_name'])
                    ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                    ->where($this->db->quoteInto(
                        'dpdl.property_uuid = ?',
                        Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db)
                    ));
                $plain->datalist = $this->db->fetchOne($query);
            }
        }

        if ($plain->parent_uuid !== null) {
            $plain->parent_uuid = Uuid::fromBytes(
                Db\DbUtil::binaryResult($plain->parent_uuid)
            )->toString();
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
                Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $this->db)
            );

        foreach (DirectorProperty::loadAll($this->connection, $query) as $item) {
            foreach ($item->fetchItemsFromDb() as $nestedItem) {
                $item->items[] = $nestedItem;
            }

            $this->items[] = $item;
        }

        return $this->items;
    }

    /**
     * Re-query this property's children directly from the database, bypassing the
     * $items cache fetchItemsFromDb() may already hold with just a snapshot's own
     * children, as a basket import leaves it
     *
     * @return DirectorProperty[]
     */
    public function fetchExistingChildrenFromDb(): array
    {
        $uuid = $this->get('uuid');
        if ($uuid === null) {
            return [];
        }

        $uuid = Uuid::fromBytes($uuid);
        $query = $this->db->select()
            ->from('director_property')
            ->where(
                'parent_uuid = ?',
                Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $this->db)
            );

        return DirectorProperty::loadAll($this->connection, $query);
    }

    public function getDatalist(): ?DirectorDatalist
    {
        if ($this->datalist) {
            return $this->datalist;
        }

        if (str_starts_with($this->get('value_type'), 'datalist-')) {
            $query = $this->db->select()->from(['dd' => 'director_datalist'], ['list_name'])
                ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                ->where($this->db->quoteInto(
                    'dpdl.property_uuid = ?',
                    Db\DbUtil::quoteBinaryCompat($this->get('uuid'), $this->db)
                ));
            $listName = $this->db->fetchOne($query);
            if ($listName) {
                $this->datalist = DirectorDatalist::load($listName, $this->connection);
            }
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
     * Resolve a datalist referenced by name during basket import
     *
     * If no datalist with this name exists yet in the target database, a new one is created
     * and persisted immediately, so that it has a uuid by the time onStore() links it to this
     * property, and so that sibling properties referencing the same not-yet-existing list name
     * resolve to the very same row instead of each creating their own duplicate.
     *
     * @param mixed $datalistName
     * @param Db    $db
     *
     * @return ?DirectorDatalist
     */
    private static function resolveImportedDatalist($datalistName, Db $db): ?DirectorDatalist
    {
        $datalist = DirectorDatalist::loadOptional($datalistName, $db);
        if (! $datalist && is_string($datalistName)) {
            $datalist = DirectorDatalist::create([
                'list_name' => $datalistName,
                'owner'     => static::currentUsername(),
            ], $db);
            $datalist->store($db);
        }

        return $datalist;
    }

    private static function currentUsername(): string
    {
        $auth = Auth::getInstance();

        return $auth->isAuthenticated() ? $auth->getUser()->getUsername() : '<unknown>';
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
                $datalist = static::resolveImportedDatalist($plain->datalist, $db);
                unset($plain->datalist);
            }

            $candidate = DirectorProperty::loadWithUniqueId($uuid, $db);
            if ($candidate) {
                assert($candidate instanceof DirectorProperty);
                if (isset($plain->parent_uuid)) {
                    $plain->parent_uuid = Uuid::fromString($plain->parent_uuid)->getBytes();
                }

                $candidate->setProperties((array) $plain);
                if ($datalist) {
                    $candidate->datalist = $datalist;
                }

                $candidate->items = $candidate->importItems((array) $items, $db);

                return $candidate;
            }
        }

        // If DirectorProperty has no UUID (mainly for property children),
        // load it from the database using the "key_name" property
        $query = $dba->select()->from('director_property')->where('key_name = ?', $plain->key_name);
        if (isset($plain->parent_uuid)) {
            $query->where('parent_uuid = ?', Db\DbUtil::quoteBinaryCompat($plain->parent_uuid, $db->getDbAdapter()));
        } else {
            $query->where('parent_uuid is NULL');
        }

        $dbRow = $dba->fetchRow($query);
        if ($dbRow !== false) {
            $candidate = DirectorProperty::fromDbRow($dbRow, $db);
            $export = $candidate->export();
            if (isset($export->parent_uuid)) {
                $exportParent = DirectorProperty::loadWithUniqueId(Uuid::fromString($export->parent_uuid), $db);
                if ($exportParent === null) {
                    // Parent no longer exists (orphaned reference, no FK enforces this link);
                    // leave parent_uuid in place instead of crashing on a null dereference.
                } else {
                    $export->parent = $exportParent->get('key_name');
                    unset($export->parent_uuid);
                }
            }

            CompareBasketObject::normalize($export);
            $plainParentUuid = $plain->parent_uuid ?? null;
            if (isset($plain->parent_uuid)) {
                $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($plain->parent_uuid), $db);
                if ($parent === null) {
                    // $export's parent_uuid is already a UUID string at this point (see
                    // export()); match that representation here too, or the equals() call
                    // below tries to JSON-encode raw binary bytes and crashes. The raw bytes
                    // form is restored a few lines down before create() needs it.
                    unset($plain->parent);
                    $plain->parent_uuid = Uuid::fromBytes($plainParentUuid)->toString();
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

    /**
     * @throws InvalidArgumentException if a nested property is being stored with a value_type
     *                                  that may only be used at the top level, or a 'sensitive'
     *                                  item type is used under a dynamic-array
     */
    protected function beforeStore(): void
    {
        $parentUuid = $this->get('parent_uuid');
        if ($parentUuid === null) {
            return;
        }

        $valueType = $this->get('value_type');

        if (in_array($valueType, self::NON_NESTABLE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                "'%s' can only be used as a top-level custom variable; it cannot be nested inside"
                . " a fixed-array, fixed-dictionary, dynamic-array or another dynamic-dictionary",
                $valueType
            ));
        }

        // A dynamic-array shows every entry in the clear, so a sensitive item here
        // could never actually be hidden.
        if ($valueType === 'sensitive') {
            $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $this->connection);
            if ($parent !== null && $parent->get('value_type') === 'dynamic-array') {
                throw new InvalidArgumentException("'sensitive' cannot be used as the item type of a dynamic-array");
            }
        }
    }

    protected function onStore(): void
    {
        $db = $this->db;
        $propertyUuid = Db\DbUtil::quoteBinaryCompat($this->get('uuid'), $db);
        // Fetch the datalist before deleting the link row
        $datalist = $this->getDatalist();
        $db->delete(
            'director_property_datalist',
            $db->quoteInto('property_uuid = ?', $propertyUuid)
        );

        if ($datalist) {
            $db->insert(
                'director_property_datalist',
                [
                    'property_uuid' => $propertyUuid,
                    'list_uuid'     => Db\DbUtil::quoteBinaryCompat($datalist->get('uuid'), $db),
                ]
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

                $child = DirectorProperty::import($value, $db);
                if ($nestedItems) {
                    $child->items = $this->importItems($nestedItems, $db);
                }

                $itemCandidates[$key] = $child;

                continue;
            }

            assert($itemCandidate instanceof DirectorProperty);
            if (isset($value->parent_uuid)) {
                $value->parent_uuid = Uuid::fromString($value->parent_uuid)->getBytes();
            }

            $datalist = null;
            if (isset($value->datalist)) {
                $datalist = static::resolveImportedDatalist($value->datalist, $db);
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
