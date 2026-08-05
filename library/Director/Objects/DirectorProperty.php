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

    /** @var DirectorProperty[]|null null is "not loaded yet", empty array is "really has no children" */
    private ?array $items = null;

    /** @var ?DirectorDatalist */
    private $datalist = null;

    /** True once we actually looked up the datalist, so null can mean "none" instead of "not yet" */
    private bool $datalistResolved = false;

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
        if ($this->items !== null) {
            return $this->items;
        }

        $uuid = $this->get('uuid');
        if ($uuid === null) {
            return [];
        }

        $this->items = [];

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
        if ($this->datalistResolved) {
            return $this->datalist;
        }

        $this->datalistResolved = true;

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

    /**
     * Assign the datalist resolved for this property during import
     *
     * Swapping or clearing the list touches no tracked column, so store() would think
     * nothing changed and skip onStore(), the place that actually writes the link. Mark
     * the property modified here when the list really changed, so it still gets stored.
     *
     * @param ?DirectorDatalist $datalist
     */
    public function assignDatalist(?DirectorDatalist $datalist): void
    {
        if ($this->hasBeenLoadedFromDb()) {
            $current = $this->getDatalist();
            $currentUuid = $current ? $current->get('uuid') : null;
            $newUuid = $datalist ? $datalist->get('uuid') : null;

            if ($currentUuid !== $newUuid) {
                $this->hasBeenModified = true;
            }
        }

        $this->datalist = $datalist;
        $this->datalistResolved = true;
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
     * If it doesn't exist on the target yet, a new one is created and stored right away, so
     * onStore() has a uuid to link against and siblings sharing the same new name reuse the same
     * row instead of each creating their own. A diff/comparison lookup passes $persist false
     * though, it only wants an in-memory datalist and must not write anything.
     *
     * @param mixed $datalistName
     * @param Db    $db
     * @param bool  $persist
     *
     * @return ?DirectorDatalist
     */
    private static function resolveImportedDatalist($datalistName, Db $db, bool $persist = true): ?DirectorDatalist
    {
        $datalist = DirectorDatalist::loadOptional($datalistName, $db);
        if (! $datalist && is_string($datalistName)) {
            $datalist = DirectorDatalist::create([
                'list_name' => $datalistName,
                'owner'     => self::currentUsername(),
            ], $db);
            if ($persist) {
                $datalist->store($db);
            }
        }

        return $datalist;
    }

    private static function currentUsername(): string
    {
        $auth = Auth::getInstance();

        return $auth->isAuthenticated() ? $auth->getUser()->getUsername() : '<unknown>';
    }

    /**
     * @param bool $persist Pass false for a diff/comparison lookup, so a not-yet-existing
     *                       datalist stays in memory instead of getting stored
     *
     * @throws NotFoundError
     */
    public static function import(stdClass $plain, Db $db, bool $persist = true): DirectorProperty
    {
        $dba = $db->getDbAdapter();
        $uuid = $plain->uuid ?? null;
        $datalist = null;
        $datalistProvided = false;
        // DirectorProperty items (children)
        $items = $plain->items ?? [];
        unset($plain->items);

        // If DirectorProperty has a UUID, load it from the database using the "uuid" property
        if ($uuid) {
            $uuid = Uuid::fromString($uuid);
            if (isset($plain->datalist)) {
                $datalistProvided = true;
                $datalist = self::resolveImportedDatalist($plain->datalist, $db, $persist);
                unset($plain->datalist);
            }

            $candidate = self::loadWithUniqueId($uuid, $db);
            if ($candidate) {
                assert($candidate instanceof DirectorProperty);
                if (isset($plain->parent_uuid)) {
                    $plain->parent_uuid = Uuid::fromString($plain->parent_uuid)->getBytes();
                }

                $candidate->setProperties((array) $plain);
                if ($datalistProvided) {
                    $candidate->assignDatalist($datalist);
                }

                $candidate->items = $candidate->importItems((array) $items, $db, $persist);

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
            $candidate = self::fromDbRow($dbRow, $db);

            unset($plain->uuid);
            $candidate->setProperties((array) $plain);

            if ($datalistProvided) {
                $candidate->assignDatalist($datalist);
            }

            $candidate->items = $candidate->importItems((array) $items, $db, $persist);

            return $candidate;
        }

        $property = static::create((array) $plain, $db);

        if ($datalistProvided) {
            $property->assignDatalist($datalist);
        }

        if ($items) {
            $property->items = $property->importItems((array) $items, $db, $persist);
        }

        return $property;
    }

    /**
     * Whether storing this property switches its value_type into an unmasked list type
     *
     * A basket restore needs this to pick a safe store order. A property entering an
     * unmasked type needs its children reconciled before it stores. A property leaving
     * one needs to store first, or a child turning sensitive fails its own check against
     * the still-old parent type. No single order works for both.
     *
     * @return bool
     */
    public function entersUnmaskedListType(): bool
    {
        if (! in_array($this->get('value_type'), self::UNMASKED_LIST_TYPES, true)) {
            return false;
        }

        return ! in_array($this->getOriginalProperty('value_type'), self::UNMASKED_LIST_TYPES, true);
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

        $this->assertNoParentCycle($parentUuid);

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
     * Reject a parent that is this property itself or one of its own descendants
     *
     * Walks up from the proposed parent. Landing back on our own uuid means
     * that parent is really one of our descendants, which would close a loop.
     *
     * @throws InvalidArgumentException
     */
    private function assertNoParentCycle(string $parentUuid): void
    {
        $ownUuid = $this->get('uuid');
        if ($ownUuid === null) {
            return;
        }

        $visited = [];
        $currentUuid = $parentUuid;

        while ($currentUuid !== null) {
            if ($currentUuid === $ownUuid) {
                throw new InvalidArgumentException(
                    'A property cannot be its own parent, directly or through one of its own children'
                );
            }

            $visitedKey = bin2hex($currentUuid);
            if (isset($visited[$visitedKey])) {
                // Already a broken chain further up, not this store's problem to solve
                break;
            }

            $visited[$visitedKey] = true;

            $parent = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($currentUuid), $this->connection);
            $currentUuid = $parent?->get('parent_uuid');
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
     * @param bool  $persist Whether a not-yet-existing referenced datalist may be created and
     *                       stored right away, see import()
     *
     * @return array
     */
    private function importItems(array $items, Db $db, bool $persist = true): array
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

            // The export carries the exporting instance's own parent_uuid, which can
            // point at a UUID this property was reconciled away from. Always re-parent
            // onto this property's real, local UUID instead of trusting the export.
            $value->parent_uuid = $this->get('uuid');

            $itemUUid = Uuid::fromString($itemUUid);
            $itemCandidate = DirectorProperty::loadWithUniqueId($itemUUid, $db);
            if (! $itemCandidate) {
                $child = DirectorProperty::import($value, $db, $persist);
                $child->items = $child->importItems($nestedItems, $db, $persist);

                $itemCandidates[$key] = $child;

                continue;
            }

            assert($itemCandidate instanceof DirectorProperty);

            $datalist = null;
            $datalistProvided = false;
            if (isset($value->datalist)) {
                $datalistProvided = true;
                $datalist = self::resolveImportedDatalist($value->datalist, $db, $persist);
                unset($value->datalist);
            }

            $itemCandidate->setProperties((array) $value);

            if ($datalistProvided) {
                $itemCandidate->assignDatalist($datalist);
            }

            $itemCandidate->items = $itemCandidate->importItems($nestedItems, $db, $persist);

            $itemCandidates[$key] = $itemCandidate;
        }

        return $itemCandidates;
    }
}
