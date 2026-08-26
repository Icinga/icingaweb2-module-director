<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
use Icinga\Module\Director\CustomVariable\PropertyDetachmentCleaner;
use Icinga\Module\Director\CustomVariable\PropertyRenameOrder;
use Icinga\Module\Director\CustomVariable\PropertySchemaDiff;
use Icinga\Module\Director\CustomVariable\PropertyValueMigration;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;
use LogicException;
use Ramsey\Uuid\Uuid;

class BasketSnapshotCustomVariableResolver
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

    /** @var bool */
    protected $newPropertiesStored = false;

    /** @var bool */
    protected $readOnly;

    /** @var int values left in place because a legacy Data Field still owns them */
    protected $keptValuesCount = 0;

    /** @var PropertyValueMigration[] one plan per root, run after every object is restored */
    protected $pendingValueMigrations = [];

    /**
     * @param BasketSnapshot|BasketSnapshot[] $objects  One or more snapshot objects to resolve
     * @param Db                              $targetDb
     * @param bool                            $readOnly True for a diff/comparison, which never calls
     *                                                   storeNewProperties() on this resolver and so
     *                                                   must not let import() persist a new datalist
     */
    public function __construct($objects, Db $targetDb, bool $readOnly = false)
    {
        $this->objects = (array) $objects;
        $this->targetDb = $targetDb;
        $this->readOnly = $readOnly;
    }

    /**
     * Load all custom properties from the DB.
     *
     * @param Db $db
     *
     * @return array<string, DirectorProperty>
     */
    public function loadCurrentProperties(Db $db): array
    {
        $properties = [];
        foreach ($this->getRequiredUuids() as $uuid) {
            $property = DirectorProperty::loadWithUniqueId(Uuid::fromString($uuid), $db);
            if ($property !== null) {
                $properties[$uuid] = $property;
            }
        }

        return $properties;
    }

    /**
     * Store new custom properties.
     *
     * @return void
     */
    public function storeNewProperties(): void
    {
        $this->targetProperties = null; // Clear Cache
        $this->keptValuesCount = 0;
        $this->pendingValueMigrations = [];
        $cleaner = new CustomVariableValueCleaner($this->targetDb);

        foreach ($this->getTargetProperties() as $uuid => $property) {
            $wasModified = $property->hasBeenModified();

            $this->applySchemaDiff($property, $cleaner);
            $this->storeReconciled($property);

            if ($wasModified) {
                $this->uuidMap[$uuid] = Uuid::fromBytes(
                    DbUtil::binaryResult($property->get('uuid'))
                )->toString();
            }
        }

        $this->newPropertiesStored = true;
    }

    /**
     * Apply the stored-value migrations storeNewProperties() worked out
     *
     * Must run after every object in this basket has been restored. A host
     * or service still carries its own old vars, moving values any earlier
     * just gets overwritten once that runs.
     *
     * @return void
     */
    public function applyPendingValueMigrations(): void
    {
        $cleaner = new CustomVariableValueCleaner($this->targetDb);

        foreach ($this->pendingValueMigrations as $migration) {
            $this->keptValuesCount += $cleaner->applyValueMigration($migration);
        }

        $this->pendingValueMigrations = [];
    }

    /**
     * How many stored values were left in place across this restore because a
     * legacy Data Field still owns them
     *
     * @return int
     */
    public function getKeptValuesCount(): int
    {
        return $this->keptValuesCount;
    }

    /**
     * Work out what a property tree's pending changes mean for its stored values
     *
     * Has to look this up now, before storeReconciled() changes anything below
     * $root, ancestor names read off the database are only still the old ones
     * until then. The write itself is deferred, see applyPendingValueMigrations().
     *
     * A blocked change gets undone in memory by the diff itself, so a schema row
     * never ends up pointing at data that never actually moved.
     *
     * @param DirectorProperty           $root
     * @param CustomVariableValueCleaner $cleaner
     *
     * @return void
     */
    private function applySchemaDiff(DirectorProperty $root, CustomVariableValueCleaner $cleaner): void
    {
        $this->pendingValueMigrations[] = (new PropertySchemaDiff($cleaner))->diff($root);
    }

    /**
     * Relink custom properties to the new object.
     *
     * @param IcingaObject $new    The object that was just restored
     * @param object       $object The matching data from the snapshot, might not carry any custom
     *                             variables at all
     *
     * @return void
     */
    public function relinkObjectCustomProperties(IcingaObject $new, $object): void
    {
        if (! $new->supportsCustomProperties() || ! isset($object->customVariables)) {
            return;
        }

        $this->assertPropertiesHaveBeenStored();

        $customPropertyMap = $this->getUuidMap();
        $db = $this->targetDb->getDbAdapter();
        $objectUuid = DbUtil::quoteBinaryCompat($new->get('uuid'), $db);
        $type = $new->getShortTableName();

        $table = $new->getTableName() . '_property';
        $objectKey = $type . '_uuid';
        $existingCustomProperties = [];
        foreach (
            $db->fetchAll(
                $db->select()->from($table)->where("$objectKey = ?", $objectUuid)
            ) as $mapping
        ) {
            $propertyUuid = DbUtil::binaryResult($mapping->property_uuid);
            $existingCustomProperties[Uuid::fromBytes($propertyUuid)->toString()] = $mapping;
        }

        $targetProperties = $this->getTargetProperties();
        foreach ($object->customVariables as $property) {
            $propertyUuid = DbUtil::binaryResult($property->property_uuid);
            if (! isset($customPropertyMap[$propertyUuid])) {
                throw new InvalidArgumentException(
                    'Basket Snapshot contains invalid custom variable reference: ' . $propertyUuid
                );
            }

            $uuid = $customPropertyMap[$propertyUuid];

            if (isset($existingCustomProperties[$uuid])) {
                $db->update(
                    $table,
                    ['required' => $property->required ?? 'n'],
                    $db->quoteInto(
                        "$objectKey = $objectUuid AND property_uuid = ?",
                        DbUtil::quoteBinaryCompat(Uuid::fromString($uuid)->getBytes(), $db)
                    )
                );
                unset($existingCustomProperties[$uuid]);
            } else {
                $db->insert($table, [
                    $objectKey      => DbUtil::quoteBinaryCompat($new->get('uuid'), $db),
                    'property_uuid' => DbUtil::quoteBinaryCompat(Uuid::fromString($uuid)->getBytes(), $db),
                    'required' => $property->required ?? 'n',
                ]);
            }

            if (! isset($targetProperties[$propertyUuid])) {
                throw new InvalidArgumentException(
                    'Basket Snapshot contains invalid custom variable reference: ' . $propertyUuid
                );
            }

            $new->vars()->registerVarUuid($targetProperties[$propertyUuid]->get('key_name'), Uuid::fromString($uuid));
        }

        $new->vars()->storeToDb($new);

        if (! empty($existingCustomProperties)) {
            $existingCustomPropertyUuids = array_map(
                fn($uuid) => Uuid::fromString($uuid)->getBytes(),
                array_keys($existingCustomProperties)
            );
            $quotedExistingCustomPropertyUuids = DbUtil::quoteBinaryCompat($existingCustomPropertyUuids, $db);

            // a value left over from one of these can still belong to a host that
            // never got restored, dropping the attachment alone would strand it
            PropertyDetachmentCleaner::removeStaleValues($new, $quotedExistingCustomPropertyUuids, $this->targetDb);

            $db->delete(
                $table,
                $db->quoteInto(
                    "$objectKey = $objectUuid AND property_uuid IN (?)",
                    $quotedExistingCustomPropertyUuids
                )
            );
        }
    }

    /**
     * For diff purposes only, gives '(UNKNOWN)' for custom properties missing
     * in our DB
     *
     * @param object $object
     */
    public function tweakTargetUuids(object $object): void
    {
        if (! isset($object->customVariables)) {
            return;
        }

        $forward = $this->getUuidMap();
        $map = array_flip($forward);
        foreach ($object->customVariables as $property) {
            if (! isset($property->property_uuid)) {
                continue;
            }

            $uuid = $property->property_uuid;
            if (isset($map[$uuid])) {
                $property->property_uuid = $map[$uuid];
            } else {
                $property->property_uuid = '(UNKNOWN)';
            }
        }
    }

    /**
     * Get all required UUIDs for custom properties.
     *
     * @return array
     */
    protected function getRequiredUuids(): array
    {
        if ($this->requiredUuids !== null) {
            return $this->requiredUuids;
        }

        if (isset($this->objects['CustomVariable'])) {
            $this->requiredUuids = array_keys($this->objects['CustomVariable']);

            return $this->requiredUuids;
        }

        $uuids = [];
        // Get the uuids of all custom properties associated with all the objects hosts, services, etc.
        foreach ($this->objects as $objectType => $objects) {
            if (
                ! in_array(
                    $objectType,
                    ['HostTemplate', 'ServiceTemplate', 'CommandTemplate', 'NotificationTemplate', 'UserTemplate']
                )
            ) {
                continue;
            }

            foreach ($objects as $object) {
                if (! isset($object->customVariables)) {
                    continue;
                }

                foreach ($object->customVariables as $property) {
                    $uuids[$property->property_uuid] = true;
                }
            }
        }

        $this->requiredUuids = array_keys($uuids);

        return $this->requiredUuids;
    }

    /**
     * Assert that new properties have already been persisted
     *
     * calculateUuidMap() assigns a placeholder uuid to a property that has
     * not been stored yet. That placeholder is only corrected once
     * storeNewProperties() runs and writes back the real uuid, so relinking
     * before that point would write a link that points at nothing.
     *
     * @throws LogicException
     */
    protected function assertPropertiesHaveBeenStored(): void
    {
        if (! $this->newPropertiesStored && ! empty($this->getObjectsByType('CustomVariable'))) {
            throw new LogicException(
                'storeNewProperties() must run before custom properties can be relinked'
            );
        }
    }

    /**
     * Get all objects of a certain type.
     *
     * @param string $type The kind of object we're looking for, like host or service
     *
     * @return object[]
     */
    protected function getObjectsByType($type): array
    {
        if (! isset($this->objects[$type])) {
            return [];
        }

        return (array) $this->objects[$type];
    }

    /**
     * Get all target properties.
     *
     * @return DirectorProperty[]
     */
    protected function getTargetProperties(): array
    {
        if ($this->targetProperties === null) {
            $this->calculateUuidMap();
        }

        return $this->targetProperties;
    }

    /**
     * Get the target property for one basket uuid, null if there is none
     *
     * @param string $uuid The property's uuid as used in the basket's own CustomVariable map
     *
     * @return ?DirectorProperty
     */
    public function getTargetProperty(string $uuid): ?DirectorProperty
    {
        return $this->getTargetProperties()[$uuid] ?? null;
    }

    /**
     * Get the UUID map for object property UUIDs.
     *
     * @return array
     */
    protected function getUuidMap(): array
    {
        if ($this->uuidMap === null) {
            $this->calculateUuidMap();
        }

        return $this->uuidMap;
    }

    /**
     * Calculate the UUID map for object property UUIDs.
     *
     * @return void
     */
    protected function calculateUuidMap(): void
    {
        $this->uuidMap = [];
        $this->targetProperties = [];
        foreach ($this->getObjectsByType('CustomVariable') as $uuid => $object) {
            // import() prepares the object but does not persist it; $new->get('uuid') may be a
            // freshly generated UUID that is only valid after storeNewProperties() is called.
            // A read-only resolver never calls storeNewProperties() at all, so it also can't
            // let import() create and store a new datalist here.
            $new = DirectorProperty::import($object, $this->targetDb, ! $this->readOnly);
            if ($new->hasBeenLoadedFromDb()) {
                $newUuid = Uuid::fromBytes(
                    Db\DbUtil::binaryResult($new->get('uuid'))
                )->toString();
            } else {
                $newUuid = Uuid::uuid4()->toString();
            }

            $this->uuidMap[$uuid] = $newUuid;
            $this->targetProperties[$uuid] = $new;
        }
    }

    /**
     * Store $node and reconcile its children in whichever order beforeStore() needs
     *
     * A node entering an unmasked list type needs its children reconciled first, or
     * beforeStore() still sees a sensitive child we're about to delete. A node leaving
     * one needs to store first, or a child turning sensitive fails its own check against
     * the still-old parent type. Runs at every level, since a nested item can retype
     * the same way its own children can.
     *
     * @param DirectorProperty $node
     */
    private function storeReconciled(DirectorProperty $node): bool
    {
        if (! $node->hasBeenLoadedFromDb()) {
            // Nothing points at it yet, store it first so its children have a parent uuid.
            $modified = false;
            if ($node->hasBeenModified()) {
                $node->store();
                $modified = true;
            }

            return $this->reconcileChildren($node) || $modified;
        }

        $entersUnmaskedListType = $node->entersUnmaskedListType();
        $modified = false;

        if ($entersUnmaskedListType && $this->reconcileChildren($node)) {
            $modified = true;
        }

        if ($node->hasBeenModified()) {
            $node->store();
            $modified = true;
        }

        if (! $entersUnmaskedListType && $this->reconcileChildren($node)) {
            $modified = true;
        }

        return $modified;
    }

    private function reconcileChildren(DirectorProperty $property): bool
    {
        $items = $property->fetchItemsFromDb();
        $keep = [];
        foreach ($items as $item) {
            $itemUuid = $item->get('uuid');
            if ($itemUuid !== null) {
                $keep[$itemUuid] = true;
            }
        }

        $modified = false;

        // Delete removed children first. A survivor moving into a freed slot would
        // otherwise collide with the row still sitting there, and a parent retype above
        // would still see a child that's about to disappear anyway.
        foreach ($property->fetchExistingChildrenFromDb() as $existingChild) {
            if (! isset($keep[$existingChild->get('uuid')])) {
                $existingChild->delete();
                $modified = true;
            }
        }

        foreach ($this->orderForSafeRename($items) as $item) {
            if ($this->storeReconciled($item)) {
                $modified = true;
            }
        }

        return $modified;
    }

    /**
     * Order renamed children so store() can write their final key_name directly
     *
     * Only a real cycle (A wants B's name, B wants A's) needs a placeholder. A plain
     * chain just needs storing tail first, so nobody takes a name still in use.
     *
     * @param DirectorProperty[] $items
     *
     * @return DirectorProperty[]
     */
    private function orderForSafeRename(array $items): array
    {
        $renames = [];
        foreach ($items as $item) {
            if (! $item->hasBeenLoadedFromDb()) {
                continue;
            }

            if ($item->getOriginalProperty('key_name') === $item->get('key_name')) {
                continue;
            }

            $renames[$this->uuidString($item)] = [
                'old' => $item->getOriginalProperty('key_name'),
                'new' => $item->get('key_name'),
            ];
        }

        if (empty($renames)) {
            return $items;
        }

        $itemsByUuid = [];
        foreach ($items as $item) {
            $itemsByUuid[$this->uuidString($item)] = $item;
        }

        $result = (new PropertyRenameOrder())->resolve($renames);

        // Park a real cycle's members under their own uuid first, so the rename below
        // never fights over a slot that's still taken. Raw update on purpose, store()
        // would also flush a pending retype too early.
        if (! empty($result['cycles'])) {
            $db = $this->targetDb->getDbAdapter();
            foreach ($result['cycles'] as $uuid) {
                $db->update(
                    'director_property',
                    ['key_name' => '__director_restore__' . $uuid],
                    $db->quoteInto(
                        'uuid = ?',
                        DbUtil::quoteBinaryCompat($itemsByUuid[$uuid]->get('uuid'), $db)
                    )
                );
            }
        }

        $ordered = [];
        foreach ($result['order'] as $uuid) {
            $ordered[] = $itemsByUuid[$uuid];
        }

        foreach ($items as $item) {
            if (! isset($renames[$this->uuidString($item)])) {
                $ordered[] = $item;
            }
        }

        return $ordered;
    }

    /**
     * Returns a string representation of the given item's uuid.
     *
     * @param DirectorProperty $item
     *
     * @return string
     */
    private function uuidString(DirectorProperty $item): string
    {
        return Uuid::fromBytes(DbUtil::binaryResult($item->get('uuid')))->toString();
    }
}
