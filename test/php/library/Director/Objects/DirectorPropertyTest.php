<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for DirectorProperty model
 */
class DirectorPropertyTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    /** @var string[] key_names of root properties created in tests (for tearDown) */
    private array $createdKeyNames = [];

    /** @var string[] list_names of datalists created in tests (for tearDown) */
    private array $createdListNames = [];

    public function testStringPropertyPersistsAndReloads(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('env', 'string', 'Environment', $db);
        $property->store();

        $uuid = Uuid::fromBytes($property->get('uuid'));
        $loaded = DirectorProperty::loadWithUniqueId($uuid, $db);

        $this->assertNotNull($loaded);
        $this->assertEquals(self::PREFIX . 'env', $loaded->get('key_name'));
        $this->assertEquals('string', $loaded->get('value_type'));
        $this->assertEquals('Environment', $loaded->get('label'));
    }

    public function testGetDatalistReturnsNullWithoutThrowingWhenNoLinkExistsYet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        // Created directly via create()->store(), not import(): $datalist is never
        // pre-populated, and no director_property_datalist row exists for this uuid yet.
        $property = $this->makeProperty('unlinked_datalist', 'datalist-strict', 'Unlinked', $db);
        $property->store();

        $this->assertNull($property->getDatalist());
    }

    public function testDynamicArrayPropertyWithChildItem(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('http_vhosts', 'dynamic-array', 'HTTP Vhosts', $db);
        $parent->store();

        $parentUuid = $parent->get('uuid');
        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parentUuid,
            'value_type'  => 'string',
        ], $db);
        $child->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();

        $this->assertCount(1, $items);
        $this->assertEquals('string', $items[0]->get('value_type'));
    }

    public function testFixedDictionaryWithSubfields(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('disk_check', 'fixed-dictionary', 'Disk Check', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        foreach (['warn', 'crit'] as $fieldName) {
            $child = DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $fieldName,
                'parent_uuid' => $parentUuid,
                'value_type'  => 'string',
            ], $db);
            $child->store();
        }

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();
        $childKeys = array_map(fn($c) => $c->get('key_name'), $items);
        sort($childKeys);

        $this->assertCount(2, $items);
        $this->assertEquals(['crit', 'warn'], $childKeys);
    }

    public function testDynamicDictionaryNestingIsOneLevelOnly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('disk_checks', 'dynamic-dictionary', 'Disk Checks', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        foreach (['mount_point', 'warn', 'crit'] as $fieldName) {
            $child = DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $fieldName,
                'parent_uuid' => $parentUuid,
                'value_type'  => 'string',
            ], $db);
            $child->store();
        }

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        foreach ($reloaded->fetchItemsFromDb() as $child) {
            $this->assertNotEquals(
                'dynamic-dictionary',
                $child->get('value_type'),
                "Child of dynamic-dictionary must not itself be dynamic-dictionary (one-level nesting rule)"
            );
        }
    }

    public function testDatalistStrictAssociatesDatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $listName = self::PREFIX . 'environments';
        $this->makeDatalist($listName, $db)->store();
        $property = $this->importPropertyWithDatalist('env_choices', 'datalist-strict', 'Env Choices', $listName, $db);

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $linked = $reloaded->getDatalist();

        $this->assertNotNull($linked);
        $this->assertEquals($listName, $linked->get('list_name'));
    }

    public function testDatalistNonStrictAssociatesDatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $listName = self::PREFIX . 'env_suggest';
        $this->makeDatalist($listName, $db)->store();
        $property = $this->importPropertyWithDatalist(
            'env_suggest',
            'datalist-non-strict',
            'Env Suggest',
            $listName,
            $db
        );

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $linked = $reloaded->getDatalist();

        $this->assertNotNull($linked);
        $this->assertEquals($listName, $linked->get('list_name'));
    }

    public function testDatalistStrictExportIncludesDatalistName(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $listName = self::PREFIX . 'export_list';
        $this->makeDatalist($listName, $db)->store();
        $property = $this->importPropertyWithDatalist('env_export', 'datalist-strict', 'Env Export', $listName, $db);

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $exported = $reloaded->export();

        $this->assertTrue(property_exists($exported, 'datalist'));
        $this->assertEquals($listName, $exported->datalist);
    }

    public function testReSavingDatalistPropertyLoadedFromDbDoesNotBreakLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $listName = self::PREFIX . 'resave_list';
        $this->makeDatalist($listName, $db)->store();
        $property = $this->importPropertyWithDatalist('env_resave', 'datalist-strict', 'Env Resave', $listName, $db);

        // Load the way ordinary (non-import) code paths do, e.g. a plain edit or
        // BasketSnapshotCustomVariableResolver::restoreCustomPropertyItems(). $datalist is NOT
        // pre-populated on this instance, unlike objects returned by DirectorProperty::import().
        $loaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $loaded->set('label', 'Env Resave Updated');
        $loaded->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $this->assertNotNull(
            $reloaded->getDatalist(),
            'Re-saving a datalist property loaded without import() must not drop its datalist link'
        );
        $this->assertEquals($listName, $reloaded->getDatalist()->get('list_name'));
    }

    public function testDatalistImportRestoresDatalistLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $listName = self::PREFIX . 'import_list';
        $this->makeDatalist($listName, $db)->store();
        $property = $this->importPropertyWithDatalist('env_import', 'datalist-strict', 'Env Import', $listName, $db);

        $exported = $property->export();
        $originalUuid = $exported->uuid;

        // Wipe the property from DB entirely, then re-import from the snapshot.
        // This exercises the create() path inside import(), which does set $property->datalist.
        $dba = $db->getDbAdapter();
        $uuidBytes = $property->get('uuid');
        $quotedUuid = DbUtil::quoteBinaryCompat($uuidBytes, $dba);
        $dba->delete('director_property_datalist', $dba->quoteInto('property_uuid = ?', $quotedUuid));
        $dba->delete('director_property', $dba->quoteInto('uuid = ?', $quotedUuid));

        $imported = DirectorProperty::import($exported, $db);
        $imported->store();

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromString($originalUuid), $db);
        $this->assertNotNull($restored->getDatalist(), 'import() must restore the datalist link');
        $this->assertEquals($listName, $restored->getDatalist()->get('list_name'));
    }

    /**
     * Regression test: restoring a dynamic-dictionary property whose CHILD references a
     * datalist that does not exist yet in the target database must not fail with
     * "SQLSTATE[23000]: ..." error (a brand-new DirectorDatalist
     * created during import() must be persisted before onStore() reads its uuid).
     */
    public function testDatalistChildOfDynamicDictionaryIsPersistedWhenListDoesNotExistYet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentKeyName = self::PREFIX . 'dict_with_new_list_child';
        $listName = self::PREFIX . 'never_seen_list_child';
        $this->createdKeyNames[] = $parentKeyName;
        $this->createdListNames[] = $listName;

        $this->assertFalse(DirectorDatalist::exists($listName, $db), 'Precondition: datalist must not exist yet');

        $parentUuid = Uuid::uuid4()->toString();
        $plain = (object) [
            'uuid'        => $parentUuid,
            'key_name'    => $parentKeyName,
            'value_type'  => 'dynamic-dictionary',
            'label'       => 'Dict With New List Child',
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'items'       => [
                'choice' => $this->datalistItemPlain('choice', $parentUuid, $listName),
            ],
        ];

        $imported = DirectorProperty::import($plain, $db);
        $imported->store();
        foreach ($imported->fetchItemsFromDb() as $child) {
            $child->store();
        }

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromString($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();
        $this->assertCount(1, $items);

        $childDatalist = $items[0]->getDatalist();
        $this->assertNotNull(
            $childDatalist,
            'Newly created datalist referenced by a dictionary child must be persisted and linked'
        );
        $this->assertEquals($listName, $childDatalist->get('list_name'));
        $this->assertNotNull($childDatalist->get('uuid'), 'Newly created datalist must have a persisted uuid');
    }

    /**
     * Same regression as above, one level deeper: the not-yet-existing datalist is referenced
     * by a GRANDCHILD (dynamic-dictionary -> fixed-dictionary -> datalist-strict).
     */
    public function testDatalistGrandchildOfDynamicDictionaryIsPersistedWhenListDoesNotExistYet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentKeyName = self::PREFIX . 'dict_with_new_list_grandchild';
        $listName = self::PREFIX . 'never_seen_list_grandchild';
        $this->createdKeyNames[] = $parentKeyName;
        $this->createdListNames[] = $listName;

        $this->assertFalse(DirectorDatalist::exists($listName, $db), 'Precondition: datalist must not exist yet');

        $parentUuid = Uuid::uuid4()->toString();
        $groupUuid = Uuid::uuid4()->toString();
        $plain = (object) [
            'uuid'        => $parentUuid,
            'key_name'    => $parentKeyName,
            'value_type'  => 'dynamic-dictionary',
            'label'       => 'Dict With New List Grandchild',
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'items'       => [
                'group' => (object) [
                    'uuid'        => $groupUuid,
                    'key_name'    => 'group',
                    'value_type'  => 'fixed-dictionary',
                    'label'       => null,
                    'parent_uuid' => $parentUuid,
                    'category'    => null,
                    'description' => null,
                    'items'       => [
                        'choice' => $this->datalistItemPlain('choice', $groupUuid, $listName),
                    ],
                ],
            ],
        ];

        $imported = DirectorProperty::import($plain, $db);
        $imported->store();
        foreach ($imported->fetchItemsFromDb() as $child) {
            $child->store();
            foreach ($child->fetchItemsFromDb() as $grandchild) {
                $grandchild->store();
            }
        }

        $reloadedGroup = DirectorProperty::loadWithUniqueId(Uuid::fromString($groupUuid), $db);
        $grandchildren = $reloadedGroup->fetchItemsFromDb();
        $this->assertCount(1, $grandchildren);

        $grandchildDatalist = $grandchildren[0]->getDatalist();
        $this->assertNotNull(
            $grandchildDatalist,
            'Newly created datalist referenced by a dictionary grandchild must be persisted and linked'
        );
        $this->assertEquals($listName, $grandchildDatalist->get('list_name'));
        $this->assertNotNull($grandchildDatalist->get('uuid'), 'Newly created datalist must have a persisted uuid');
    }

    public function testExportRoundTrip(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('disk_rt', 'fixed-dictionary', 'Disk RT', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        foreach (['warn', 'crit'] as $fieldName) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $fieldName,
                'parent_uuid' => $parentUuid,
                'value_type'  => 'string',
            ], $db)->store();
        }

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $exported = $reloaded->export();
        $originalUuid = $exported->uuid;

        // Wipe and re-import
        $dba = $db->getDbAdapter();
        $quotedParentUuid = DbUtil::quoteBinaryCompat($parentUuid, $dba);
        $dba->delete('director_property', $dba->quoteInto('parent_uuid = ?', $quotedParentUuid));
        $dba->delete('director_property', $dba->quoteInto('uuid = ?', $quotedParentUuid));

        $imported = DirectorProperty::import($exported, $db);
        $imported->store();
        foreach ($imported->fetchItemsFromDb() as $child) {
            $child->store();
        }

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromString($originalUuid), $db);
        $this->assertNotNull($restored);
        $this->assertEquals('fixed-dictionary', $restored->get('value_type'));
        $this->assertEquals(self::PREFIX . 'disk_rt', $restored->get('key_name'));

        $childKeys = array_map(fn($c) => $c->get('key_name'), $restored->fetchItemsFromDb());
        sort($childKeys);
        $this->assertEquals(['crit', 'warn'], $childKeys);
    }

    public function testImportWithOrphanedParentDoesNotCrash(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $parent = $this->makeProperty('orphan_parent', 'fixed-dictionary', 'Orphan Parent', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $childKeyName = self::PREFIX . 'orphan_child';
        $this->createdKeyNames[] = $childKeyName;
        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => $childKeyName,
            'parent_uuid' => $parentUuid,
            'value_type'  => 'string',
        ], $db)->store();

        // Simulate a dangling parent reference: director_property.parent_uuid has no FK
        // enforcing this link (see schema/mysql.sql), so this state is reachable in practice.
        $dba->delete(
            'director_property',
            $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($parentUuid, $dba))
        );

        // This branch expects parent_uuid as raw bytes (matching how the equivalent
        // lookup for $plain->parent_uuid is used at Uuid::fromBytes() a few lines down
        // in import()), unlike the has-uuid branch's string-form convention.
        $plain = (object) [
            'key_name'    => $childKeyName,
            'parent_uuid' => $parentUuid,
            'value_type'  => 'string',
        ];

        $imported = DirectorProperty::import($plain, $db);

        $this->assertInstanceOf(DirectorProperty::class, $imported);
    }

    public function testImportIsIdempotent(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('env_idem', 'string', 'Env Idempotent', $db);
        $property->store();

        $exported = $property->export();

        // First import
        $first = DirectorProperty::import($exported, $db);
        if ($first->hasBeenModified()) {
            $first->store();
        }

        // Second import
        $second = DirectorProperty::import($exported, $db);
        if ($second->hasBeenModified()) {
            $second->store();
        }

        $uuidBytes = $property->get('uuid');
        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('uuid = ?', DbUtil::quoteBinaryCompat($uuidBytes, $dba))
        );

        $this->assertEquals(1, (int) $count, 'import() must not create duplicate rows');
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            foreach ($this->createdKeyNames as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $uuid = DbUtil::binaryResult($row->uuid);
                    $descendants = $this->collectDescendantUuids($uuid, $dba);
                    foreach (array_merge([$uuid], $descendants) as $descendantUuid) {
                        $dba->delete(
                            'director_property_datalist',
                            $dba->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($descendantUuid, $dba))
                        );
                    }
                    foreach ($descendants as $descendantUuid) {
                        $dba->delete(
                            'director_property',
                            $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($descendantUuid, $dba))
                        );
                    }
                }
                $dba->delete('director_property', $dba->quoteInto('key_name = ?', $keyName));
            }

            foreach ($this->createdListNames as $listName) {
                if (DirectorDatalist::exists($listName, $db)) {
                    DirectorDatalist::load($listName, $db)->delete();
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Recursively collect the raw binary UUIDs of all descendants (children, grandchildren, ...)
     * of the property with the given raw binary UUID, not including $uuid itself.
     */
    private function collectDescendantUuids(string $uuid, $dba): array
    {
        $descendants = [];
        $parents = [$uuid];

        while (! empty($parents)) {
            $children = $dba->fetchCol(
                $dba->select()->from('director_property', ['uuid'])
                    ->where('parent_uuid IN (?)', DbUtil::quoteBinaryCompat($parents, $dba))
            );
            $children = array_map([DbUtil::class, 'binaryResult'], $children);

            $descendants = array_merge($descendants, $children);
            $parents = $children;
        }

        return $descendants;
    }

    private function makeProperty(string $suffix, string $valueType, string $label, Db $db): DirectorProperty
    {
        $keyName = self::PREFIX . $suffix;
        $this->createdKeyNames[] = $keyName;

        return DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $keyName,
            'value_type' => $valueType,
            'label'      => $label,
        ], $db);
    }

    private function makeDatalist(string $listName, Db $db): DirectorDatalist
    {
        $this->createdListNames[] = $listName;

        return DirectorDatalist::create(['list_name' => $listName, 'owner' => 'test'], $db);
    }

    /**
     * Build the plain export shape of a datalist-strict property nested under $parentUuid,
     * referencing a datalist by name (as DirectorProperty::export() would produce it).
     */
    private function datalistItemPlain(string $keyName, string $parentUuid, string $listName): object
    {
        return (object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => $keyName,
            'value_type'  => 'datalist-strict',
            'label'       => null,
            'parent_uuid' => $parentUuid,
            'category'    => null,
            'description' => null,
            'datalist'    => $listName,
            'items'       => [],
        ];
    }

    /**
     * Use DirectorProperty::import() to create a datalist-backed property and store it.
     * import() sets the private $datalist field, causing onStore() to insert the link row.
     */
    private function importPropertyWithDatalist(
        string $suffix,
        string $valueType,
        string $label,
        string $listName,
        Db $db
    ): DirectorProperty {
        $keyName = self::PREFIX . $suffix;
        $this->createdKeyNames[] = $keyName;
        $plain = (object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => $keyName,
            'value_type'  => $valueType,
            'label'       => $label,
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'datalist'    => $listName,
            'items'       => [],
        ];
        $property = DirectorProperty::import($plain, $db);
        $property->store();

        return $property;
    }
}
