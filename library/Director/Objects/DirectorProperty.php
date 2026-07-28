<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use stdClass;

class DirectorProperty extends DbObject
{
    /** Value types that may never be used for a nested (non-top-level) property */
    private const NON_NESTABLE_TYPES = [
        'dynamic-dictionary',
        'fixed-dictionary',
        'fixed-array',
    ];

    /** Parent value_types whose items are always rendered in the clear, never masked */
    private const UNMASKED_LIST_TYPES = ['dynamic-array', 'datalist-strict', 'datalist-non-strict'];

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
            $categoryName = $category;
            $category = DirectorDatafieldCategory::loadOptional($category, $this->getConnection());
            if ($category) {
                $this->setCategory($category);
            } else {
                $this->setCategory(DirectorDatafieldCategory::create(
                    ['category_name' => $categoryName],
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
        $obj->setBeingLoadedFromDb();
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
            // Two instances can create the same property with different uuids, so we
            // cannot tell identical from changed here. Adopt the incoming values like
            // the uuid branch above, otherwise the key_name collides on insert anyway.
            $candidate = DirectorProperty::fromDbRow($dbRow, $db);
            unset($plain->uuid);
            $candidate->setProperties((array) $plain);

            if ($datalist) {
                $candidate->datalist = $datalist;
            }

            $candidate->items = $candidate->importItems((array) $items, $db);

            return $candidate;
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
     *                                  that may only be used at the top level, a dynamic-array
     *                                  is nested inside another dynamic-array, a 'sensitive'
     *                                  item type is used under a dynamic-array or datalist, or a
     *                                  property is being (re)typed into a dynamic-array or
     *                                  datalist while it still has a 'sensitive' child
     */
    protected function beforeStore(): void
    {
        $this->persistPendingCategory();

        $valueType = $this->get('value_type');

        // The child-side check further down only fires when the child itself gets stored.
        // Switching an existing dynamic-dictionary (or similar) to a dynamic-array or
        // datalist skips that check entirely, so a sensitive child already sitting there
        // would suddenly start rendering in the clear. Catch that here too.
        if (in_array($valueType, self::UNMASKED_LIST_TYPES, true)) {
            foreach ($this->fetchExistingChildrenFromDb() as $child) {
                if ($child->get('value_type') === 'sensitive') {
                    throw new InvalidArgumentException(sprintf(
                        "'%s' cannot be used here, this property has a 'sensitive' child which"
                        . " would then be rendered in the clear",
                        $valueType
                    ));
                }
            }
        }

        $parentUuid = $this->get('parent_uuid');
        if ($parentUuid === null) {
            return;
        }

        if (in_array($valueType, self::NON_NESTABLE_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                "'%s' can only be used as a top-level custom variable; it cannot be nested inside"
                . " a fixed-array, fixed-dictionary, dynamic-array or dynamic-dictionary",
                $valueType
            ));
        }

        if ($valueType === 'dynamic-array' || $valueType === 'sensitive') {
            $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $this->connection);
            $parentValueType = $parent?->get('value_type');

            // dynamic-array may be nested inside a fixed-array, fixed-dictionary or
            // dynamic-dictionary field, or declare a datalist's item type, but it cannot
            // nest inside itself (a dynamic-array of dynamic-arrays).
            if ($valueType === 'dynamic-array' && $parentValueType === 'dynamic-array') {
                throw new InvalidArgumentException(
                    "'dynamic-array' cannot be nested inside another dynamic-array"
                );
            }

            // A dynamic-array or datalist shows every entry in the clear, so a sensitive
            // item here could never actually be hidden.
            if ($valueType === 'sensitive' && in_array($parentValueType, self::UNMASKED_LIST_TYPES, true)) {
                throw new InvalidArgumentException(
                    "'sensitive' cannot be used as the item type of a dynamic-array or datalist"
                );
            }
        }
    }

    /**
     * A brand new category can end up hanging around only in memory, never
     * actually saved. That happens on a basket restore hitting a fresh DB,
     * the category name doesn't exist there yet. Save it here and link it,
     * so we don't just lose the name.
     *
     * @return void
     */
    private function persistPendingCategory(): void
    {
        if ($this->category === null || $this->category->hasBeenLoadedFromDb()) {
            return;
        }

        // A sibling property may have created the very same category a moment ago,
        // in the same restore, check again before inserting a duplicate name.
        $existing = DirectorDatafieldCategory::loadOptional(
            $this->category->get('category_name'),
            $this->getConnection()
        );

        if ($existing) {
            $this->category = $existing;
        } else {
            $this->category->store();
        }

        $this->set('category_id', $this->category->get('id'));
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
