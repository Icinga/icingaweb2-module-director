<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;
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

    public function testBoolPropertyPersistsAndReloads(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('in_maintenance', 'bool', 'In Maintenance', $db);
        $property->store();

        $uuid = Uuid::fromBytes($property->get('uuid'));
        $loaded = DirectorProperty::loadWithUniqueId($uuid, $db);

        $this->assertNotNull($loaded);
        $this->assertEquals(self::PREFIX . 'in_maintenance', $loaded->get('key_name'));
        $this->assertEquals('bool', $loaded->get('value_type'));
        $this->assertEquals('In Maintenance', $loaded->get('label'));
    }

    public function testKeyNameUniquenessIsCaseInsensitiveOnBothDatabases(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $lower = $this->makeProperty('case_check', 'string', 'Case Check', $db);
        $lower->store();

        $upper = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'CASE_CHECK',
            'value_type' => 'string',
        ], $db);

        try {
            $upper->store();
            $this->fail('Storing a key_name differing only by case must violate the unique constraint');
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $matchMysql = strpos($msg, 'Duplicate entry') !== false;
            $matchPostgres = strpos($msg, 'Unique violation') !== false;

            $this->assertTrue(
                $matchMysql || $matchPostgres,
                'Exception message does not tell about unique constraint violation: ' . $msg
            );
        }
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

    /**
     * @dataProvider provideNonNestableTypes
     */
    public function testContainerTypesAreRejectedByTheModelWhenNested(string $valueType): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // These rules must hold regardless of entry point (form, REST API, CLI migration,
        // basket restore), not just because CustomVariableForm's dropdown happens to never
        // offer them as a nested option. DirectorProperty::beforeStore() enforces it directly.
        $db = $this->getDb();
        $suffix = 'network_config_' . str_replace('-', '_', $valueType);
        $parent = $this->makeProperty($suffix, 'fixed-dictionary', 'Network Config', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'interfaces',
            'parent_uuid' => $parentUuid,
            'value_type'  => $valueType,
        ], $db);

        $this->expectException(InvalidArgumentException::class);
        $child->store();
    }

    public function provideNonNestableTypes(): array
    {
        return [
            'dynamic-dictionary' => ['dynamic-dictionary'],
            'fixed-dictionary'   => ['fixed-dictionary'],
            'fixed-array'        => ['fixed-array'],
        ];
    }

    /**
     * A crafted basket snapshot is the threat model the nesting guard was built for
     * (see beforeStore()), so it must be enforced on the import() -> store() path too,
     * not only on properties built directly via create().
     */
    public function testContainerTypeNestedViaBasketImportIsRejected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentKeyName = self::PREFIX . 'router_config';
        $this->createdKeyNames[] = $parentKeyName;

        $parentUuid = Uuid::uuid4()->toString();
        $plain = (object) [
            'uuid'        => $parentUuid,
            'key_name'    => $parentKeyName,
            'value_type'  => 'fixed-dictionary',
            'label'       => 'Router Config',
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'items'       => [
                'interfaces' => (object) [
                    'uuid'        => Uuid::uuid4()->toString(),
                    'key_name'    => 'interfaces',
                    'value_type'  => 'fixed-dictionary',
                    'label'       => null,
                    'parent_uuid' => $parentUuid,
                    'category'    => null,
                    'description' => null,
                    'items'       => [],
                ],
            ],
        ];

        $imported = DirectorProperty::import($plain, $db);
        $imported->store();

        $this->expectException(InvalidArgumentException::class);
        foreach ($imported->fetchItemsFromDb() as $child) {
            $child->store();
        }
    }

    /**
     * @dataProvider provideDynamicArrayNestableParentTypes
     */
    public function testDynamicArrayIsAllowedAsAFieldOfOtherContainerTypes(string $parentValueType): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // dynamic-array may be used as a field inside a fixed-array, fixed-dictionary or
        // dynamic-dictionary; it is only barred from nesting inside itself.
        $db = $this->getDb();
        $suffix = 'network_config_' . str_replace('-', '_', $parentValueType);
        $parent = $this->makeProperty($suffix, $parentValueType, 'Network Config', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'dns_servers',
            'parent_uuid' => $parentUuid,
            'value_type'  => 'dynamic-array',
        ], $db);
        $child->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();
        $this->assertCount(1, $items);
        $this->assertEquals('dynamic-array', $items[0]->get('value_type'));
    }

    public function provideDynamicArrayNestableParentTypes(): array
    {
        return [
            'fixed-array'        => ['fixed-array'],
            'fixed-dictionary'   => ['fixed-dictionary'],
            'dynamic-dictionary' => ['dynamic-dictionary'],
        ];
    }

    public function testDynamicArrayIsAllowedAsTheItemTypeOfADatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A datalist's item type may be 'dynamic-array' to declare that it accepts a list of
        // values instead of a single one (see CustomVariableValueValidator).
        $db = $this->getDb();
        $parent = $this->makeProperty('allowed_regions', 'datalist-strict', 'Allowed Regions', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $itemType = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parentUuid,
            'value_type'  => 'dynamic-array',
        ], $db);

        $itemType->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();
        $this->assertCount(1, $items);
        $this->assertEquals('dynamic-array', $items[0]->get('value_type'));
    }

    public function testDynamicArrayCannotBeNestedInsideAnotherDynamicArray(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('backup_ports', 'dynamic-array', 'Backup Ports', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parentUuid,
            'value_type'  => 'dynamic-array',
        ], $db);

        $this->expectException(InvalidArgumentException::class);
        $child->store();
    }

    public function testSensitiveIsAllowedAsAFieldOfAFixedDictionary(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Only a dynamic-array is barred from holding a sensitive item, since it renders
        // every entry in the clear; a fixed-dictionary field has no such restriction.
        $db = $this->getDb();
        $parent = $this->makeProperty('snmp_settings', 'fixed-dictionary', 'SNMP Settings', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'community_string',
            'parent_uuid' => $parentUuid,
            'value_type'  => 'sensitive',
        ], $db);
        $child->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db);
        $items = $reloaded->fetchItemsFromDb();
        $this->assertCount(1, $items);
        $this->assertEquals('sensitive', $items[0]->get('value_type'));
    }

    public function testSensitiveCannotBeNestedInsideADynamicArray(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A dynamic-array renders every entry in the clear, so a sensitive item
        // would never actually stay hidden.
        $db = $this->getDb();
        $parent = $this->makeProperty('community_strings', 'dynamic-array', 'Community Strings', $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db);

        $this->expectException(InvalidArgumentException::class);
        $child->store();
    }

    /**
     * @dataProvider provideDatalistTypes
     */
    public function testSensitiveCannotBeNestedInsideADatalist(string $datalistType): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A datalist renders every entry in the clear too, same reasoning as a dynamic-array.
        $db = $this->getDb();
        $suffix = 'community_strings_' . str_replace('-', '_', $datalistType);
        $parent = $this->makeProperty($suffix, $datalistType, 'Community Strings', $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db);

        $this->expectException(InvalidArgumentException::class);
        $child->store();
    }

    public function testSwitchingToDynamicArrayIsRejectedWhenASensitiveChildStillExists(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A dynamic-dictionary is a safe home for a sensitive child, but flipping that same
        // property to dynamic-array afterwards is not, that type shows everything in the clear.
        $db = $this->getDb();
        $parent = $this->makeProperty('secrets_holder', 'dynamic-dictionary', 'Secrets Holder', $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'password',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db);
        $child->store();

        $parent->set('value_type', 'dynamic-array');
        $this->expectException(InvalidArgumentException::class);
        $parent->store();
    }

    /**
     * @dataProvider provideDatalistTypes
     */
    public function testSwitchingToDatalistIsRejectedWhenASensitiveChildStillExists(string $datalistType): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $suffix = 'secrets_holder_' . str_replace('-', '_', $datalistType);
        $parent = $this->makeProperty($suffix, 'dynamic-dictionary', 'Secrets Holder', $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'password',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db);
        $child->store();

        $parent->set('value_type', $datalistType);
        $this->expectException(InvalidArgumentException::class);
        $parent->store();
    }

    public function testSwitchingToDynamicArrayIsAllowedWithoutASensitiveChild(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Switching type stays possible as long as nothing sensitive would end up exposed.
        $db = $this->getDb();
        $parent = $this->makeProperty('plain_holder', 'dynamic-dictionary', 'Plain Holder', $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'note',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $child->store();

        $parent->set('value_type', 'dynamic-array');
        $parent->store();

        $reloaded = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parent->get('uuid')), $db);
        $this->assertEquals('dynamic-array', $reloaded->get('value_type'));
    }

    public function provideDatalistTypes(): array
    {
        return [
            'datalist-strict'     => ['datalist-strict'],
            'datalist-non-strict' => ['datalist-non-strict'],
        ];
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
        // BasketSnapshotCustomVariableResolver::reconcileChildren(). $datalist is NOT
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
     * Restoring a dynamic-dictionary property whose CHILD references a datalist that does
     * not exist yet in the target database must not fail with "SQLSTATE[23000]: ..." error
     * (a brand-new DirectorDatalist created during import() must be persisted before
     * onStore() reads its uuid).
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
                'severity' => $this->datalistItemPlain('severity', $parentUuid, $listName),
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

    public function testDeletingAParentCascadesToItsChildren(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $parent = $this->makeProperty('cascade_parent', 'fixed-dictionary', 'Cascade Parent', $db);
        $parent->store();
        $parentUuid = $parent->get('uuid');

        $childKeyName = self::PREFIX . 'cascade_child';
        $this->createdKeyNames[] = $childKeyName;
        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => $childKeyName,
            'parent_uuid' => $parentUuid,
            'value_type'  => 'string',
        ], $db);
        $child->store();
        $childUuid = $child->get('uuid');

        $dba->delete(
            'director_property',
            $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($parentUuid, $dba))
        );

        $this->assertNull(DirectorProperty::loadWithUniqueId(Uuid::fromBytes($parentUuid), $db));
        $this->assertNull(DirectorProperty::loadWithUniqueId(Uuid::fromBytes($childUuid), $db));
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

    public function testImportReconcilesSameKeyUnderDifferentUuid(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('cross_instance_region', 'string', 'Region', $db);
        $property->store();
        $localUuid = $property->get('uuid');

        // Two instances creating the same property end up with the same key_name
        // but a different uuid, this mirrors that basket restore scenario.
        $foreign = $property->export();
        $foreign->uuid = Uuid::uuid4()->toString();

        $imported = DirectorProperty::import($foreign, $db);

        $this->assertEquals(
            Uuid::fromBytes($localUuid)->toString(),
            Uuid::fromBytes($imported->get('uuid'))->toString(),
            'import() must reconcile onto the existing local property, not create a new one'
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('key_name = ?', self::PREFIX . 'cross_instance_region')
        );
        $this->assertEquals(1, (int) $count, 'a same-key import from another instance must not create a duplicate');
    }

    public function testImportUpdatesExistingCandidateWhenContentDiffersUnderDifferentUuid(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('cross_instance_dc', 'string', 'Datacenter', $db);
        $property->store();

        $foreign = $property->export();
        $foreign->uuid = Uuid::uuid4()->toString();
        $foreign->label = 'Datacenter (renamed elsewhere)';

        $imported = DirectorProperty::import($foreign, $db);
        if ($imported->hasBeenModified()) {
            $imported->store();
        }

        $reloaded = DirectorProperty::load(self::PREFIX . 'cross_instance_dc', $db);
        $this->assertEquals(
            'Datacenter (renamed elsewhere)',
            $reloaded->get('label'),
            'import() must update the existing candidate in place when content differs'
        );
    }

    public function testPropertyCannotBeItsOwnParent(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->makeProperty('self_parent', 'string', 'Self Parent', $db);
        $property->store();

        $property->set('parent_uuid', $property->get('uuid'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be its own parent');
        $property->store();
    }

    public function testPropertyCannotBeParentedUnderItsOwnChild(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parent = $this->makeProperty('cycle_parent', 'fixed-dictionary', 'Cycle Parent', $db);
        $parent->store();

        $childKeyName = self::PREFIX . 'cycle_child';
        $this->createdKeyNames[] = $childKeyName;
        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => $childKeyName,
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $child->store();

        // Close the loop: make the parent a child of its own child.
        $parent->set('parent_uuid', $child->get('uuid'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be its own parent');
        $parent->store();
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
