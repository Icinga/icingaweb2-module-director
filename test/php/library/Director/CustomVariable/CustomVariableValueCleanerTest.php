<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class CustomVariableValueCleanerTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private const ROOT_KEY_NAME = self::PREFIX . 'contact_ips';

    private const SHARED_KEY_NAME = self::PREFIX . 'region';

    private const NESTED_ROOT_KEY_NAME = self::PREFIX . 'mailing_address';

    private const DYNAMIC_ROOT_KEY_NAME = self::PREFIX . 'contacts';

    public function testKeepPropertyInPlaceNullsFixedArraySlotWithoutReindexing(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'retype_host',
            'object_type' => 'object',
            'address'     => '192.0.2.70',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::ROOT_KEY_NAME,
            'value_type' => 'fixed-array',
            'label'      => 'Contact IPs',
        ], $db)->store();

        $itemUuids = [];
        foreach (['0', '1', '2'] as $keyName) {
            $itemUuid = Uuid::uuid4();
            $itemUuids[$keyName] = $itemUuid;
            DirectorProperty::create([
                'uuid'        => $itemUuid->getBytes(),
                'key_name'    => $keyName,
                'parent_uuid' => $rootUuid->getBytes(),
                'value_type'  => 'string',
            ], $db)->store();
        }

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => self::ROOT_KEY_NAME,
            'varvalue'      => json_encode(['10.0.0.1', '10.0.0.2', '10.0.0.3']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $cleaner = new CustomVariableValueCleaner($db);
        $cleaner->removeObjectCustomVars(
            [
                'key_name'    => '1',
                'uuid'        => $itemUuids['1']->getBytes(),
                'parent_uuid' => $rootUuid->getBytes(),
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
            ],
            [
                'key_name'    => self::ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'fixed-array',
                'label'       => 'Contact IPs',
                'description' => null,
            ],
            true
        );

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::ROOT_KEY_NAME)
        );

        $this->assertEquals(
            '["10.0.0.1",null,"10.0.0.3"]',
            $updatedValue,
            'a retyped-in-place item must be nulled out at its own index, not unset and reindexed'
        );

        $siblingKeyNames = $dba->fetchCol(
            $dba->select()->from('director_property', ['key_name'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba))
                ->order('key_name')
        );

        $this->assertEquals(
            ['0', '1', '2'],
            $siblingKeyNames,
            'siblings must keep their original key_name, retyping in place must not trigger a reindex'
        );
    }

    public function testDeleteStoredValuesKeepsValueAliveForLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'shared_name_host',
            'object_type' => 'object',
            'address'     => '192.0.2.71',
        ], $db);
        $host->store();

        // Migration skips a datafield when a same-named property already exists, so
        // both can end up pointing at the same stored value under the same varname.
        DirectorDatafield::create([
            'varname'  => self::SHARED_KEY_NAME,
            'caption'  => 'Region',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SHARED_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::SHARED_KEY_NAME,
            'varvalue' => json_encode('us-east'),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->deleteStoredValues(self::SHARED_KEY_NAME);

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::SHARED_KEY_NAME)
        );

        $this->assertEquals(
            json_encode('us-east'),
            $storedValue,
            'a value must not be wiped while a legacy Data Field still claims the same varname'
        );
        $this->assertEquals(
            1,
            $keptCount,
            'the number of values kept alive because of the conflict must be reported back'
        );
    }

    public function testDeleteStoredValuesRemovesValueWithoutLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'no_conflict_host',
            'object_type' => 'object',
            'address'     => '192.0.2.72',
        ], $db);
        $host->store();

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SHARED_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::SHARED_KEY_NAME,
            'varvalue' => json_encode('us-east'),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->deleteStoredValues(self::SHARED_KEY_NAME);

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::SHARED_KEY_NAME)
        );

        $this->assertFalse(
            $storedValue,
            'a value must still be wiped outright when no legacy Data Field shares its varname'
        );
        $this->assertEquals(0, $keptCount, 'nothing was kept, so no values must be reported back');
    }

    public function testRenameStoredValuesKeepsValueAliveForLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $newKeyName = self::PREFIX . 'zone';

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_shared_name_host',
            'object_type' => 'object',
            'address'     => '192.0.2.73',
        ], $db);
        $host->store();

        DirectorDatafield::create([
            'varname'  => self::SHARED_KEY_NAME,
            'caption'  => 'Region',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SHARED_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::SHARED_KEY_NAME,
            'varvalue' => json_encode('us-east'),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->renameStoredValues(
            self::SHARED_KEY_NAME,
            $newKeyName
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::SHARED_KEY_NAME)
        );

        $this->assertEquals(
            json_encode('us-east'),
            $storedValue,
            'a value must stay under the old varname while a legacy Data Field still claims it'
        );
        $this->assertEquals(
            1,
            $keptCount,
            'the number of values kept alive because of the conflict must be reported back'
        );
    }

    public function testRenameStoredValuesRenamesValueWithoutLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $newKeyName = self::PREFIX . 'zone';

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_no_conflict_host',
            'object_type' => 'object',
            'address'     => '192.0.2.74',
        ], $db);
        $host->store();

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SHARED_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::SHARED_KEY_NAME,
            'varvalue' => json_encode('us-east'),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->renameStoredValues(
            self::SHARED_KEY_NAME,
            $newKeyName
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', $newKeyName)
        );

        $this->assertEquals(
            json_encode('us-east'),
            $storedValue,
            'a value must be renamed outright when no legacy Data Field shares its varname'
        );
        $this->assertEquals(0, $keptCount, 'nothing was kept, so no values must be reported back');
    }

    public function testRenameStoredValuesBlocksOnNewNameConflictEvenWithNothingStoredYet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $newKeyName = self::PREFIX . 'zone';

        // The target name is already a legacy Data Field, but the property being
        // renamed has no stored values under its old name yet. Still has to block.
        DirectorDatafield::create([
            'varname'  => $newKeyName,
            'caption'  => 'Zone',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SHARED_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db)->store();

        $cleaner = new CustomVariableValueCleaner($db);

        $this->assertTrue(
            $cleaner->wouldRenameCollideWithLegacyDatafield(self::SHARED_KEY_NAME, $newKeyName),
            'a legacy Data Field under the new name must be reported as a conflict too'
        );

        $keptCount = $cleaner->renameStoredValues(self::SHARED_KEY_NAME, $newKeyName);

        $this->assertGreaterThan(
            0,
            $keptCount,
            'a new-name conflict must be reported even when nothing was stored under the old name'
        );
    }

    public function testRenameNestedStoredValuesMovesJsonKeyWithoutLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_nested_host',
            'object_type' => 'object',
            'address'     => '192.0.2.75',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::NESTED_ROOT_KEY_NAME,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Address',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'street',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::NESTED_ROOT_KEY_NAME,
            'varvalue' => json_encode(['street' => 'Main St', 'zip' => '12345']),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->renameNestedStoredValues(
            ['key_name' => 'street'],
            [
                'key_name'    => self::NESTED_ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'fixed-dictionary',
            ],
            'road'
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::NESTED_ROOT_KEY_NAME)
        );

        $this->assertEquals(
            ['road' => 'Main St', 'zip' => '12345'],
            json_decode($storedValue, true),
            'the renamed key must move to its new name inside the stored dictionary'
        );
        $this->assertEquals(0, $keptCount, 'nothing was kept, so no values must be reported back');
    }

    public function testRenameNestedStoredValuesKeepsValueAliveForLegacyDatafield(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_nested_shared_host',
            'object_type' => 'object',
            'address'     => '192.0.2.76',
        ], $db);
        $host->store();

        // A legacy Data Field can still own this varname, its values might be the
        // field's, not this property tree's, so the rename has to leave it alone.
        DirectorDatafield::create([
            'varname'  => self::NESTED_ROOT_KEY_NAME,
            'caption'  => 'Address',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::NESTED_ROOT_KEY_NAME,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Address',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'street',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::NESTED_ROOT_KEY_NAME,
            'varvalue' => json_encode(['street' => 'Main St']),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->renameNestedStoredValues(
            ['key_name' => 'street'],
            [
                'key_name'    => self::NESTED_ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'fixed-dictionary',
            ],
            'road'
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::NESTED_ROOT_KEY_NAME)
        );

        $this->assertEquals(
            json_encode(['street' => 'Main St']),
            $storedValue,
            'a value must stay untouched while a legacy Data Field still claims the root varname'
        );
        $this->assertEquals(
            1,
            $keptCount,
            'the number of values kept alive because of the conflict must be reported back'
        );
    }

    public function testRenameNestedStoredValuesAppliesToEveryDynamicDictionaryEntry(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_nested_dynamic_host',
            'object_type' => 'object',
            'address'     => '192.0.2.77',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::DYNAMIC_ROOT_KEY_NAME,
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Contacts',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'phone',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::DYNAMIC_ROOT_KEY_NAME,
            'varvalue' => json_encode([
                'ops' => ['phone' => '555-0100'],
                'noc' => ['phone' => '555-0200'],
            ]),
            'format'   => 'json',
        ]);

        $keptCount = (new CustomVariableValueCleaner($db))->renameNestedStoredValues(
            ['key_name' => 'phone'],
            [
                'key_name'    => self::DYNAMIC_ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'dynamic-dictionary',
            ],
            'mobile'
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::DYNAMIC_ROOT_KEY_NAME)
        );

        $this->assertEquals(
            [
                'noc' => ['mobile' => '555-0200'],
                'ops' => ['mobile' => '555-0100'],
            ],
            json_decode($storedValue, true),
            'the renamed key must move for every entry of a dynamic dictionary, not just the first'
        );
        $this->assertEquals(0, $keptCount, 'nothing was kept, so no values must be reported back');
    }

    public function testRenameRefusesOccupiedDestination(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_collision_host',
            'object_type' => 'object',
            'address'     => '192.0.2.78',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::NESTED_ROOT_KEY_NAME,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Address',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'road',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::NESTED_ROOT_KEY_NAME,
            'varvalue' => json_encode(['street' => 'Main St', 'road' => 'Elm St']),
            'format'   => 'json',
        ]);

        $cleaner = new CustomVariableValueCleaner($db);
        $keptCount = $cleaner->renameNestedStoredValues(
            ['key_name' => 'road'],
            [
                'key_name'    => self::NESTED_ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'fixed-dictionary',
            ],
            'street'
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::NESTED_ROOT_KEY_NAME)
        );

        $this->assertEquals(
            ['street' => 'Main St', 'road' => 'Elm St'],
            json_decode($storedValue, true),
            'a value must not be silently overwritten when its new key is already taken'
        );
        $this->assertEquals(0, $keptCount, 'a collision is not a Data Field block, it must not be reported here');
        $this->assertEquals(
            1,
            $cleaner->getRenameCollisionCount(),
            'the skipped value must be counted so the admin can be told about it'
        );
    }

    public function testDynamicRenameRefusesOccupiedDestination(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'rename_collision_dynamic_host',
            'object_type' => 'object',
            'address'     => '192.0.2.79',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::DYNAMIC_ROOT_KEY_NAME,
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Contacts',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'phone',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => self::DYNAMIC_ROOT_KEY_NAME,
            'varvalue' => json_encode([
                'ops' => ['mobile' => 'already here', 'phone' => '555-0100'],
                'noc' => ['phone' => '555-0200'],
            ]),
            'format'   => 'json',
        ]);

        $cleaner = new CustomVariableValueCleaner($db);
        $keptCount = $cleaner->renameNestedStoredValues(
            ['key_name' => 'phone'],
            [
                'key_name'    => self::DYNAMIC_ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'dynamic-dictionary',
            ],
            'mobile'
        );

        $storedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::DYNAMIC_ROOT_KEY_NAME)
        );

        $this->assertEquals(
            [
                'noc' => ['mobile' => '555-0200'],
                'ops' => ['mobile' => 'already here', 'phone' => '555-0100'],
            ],
            json_decode($storedValue, true),
            'only the colliding entry keeps its old key, an unaffected entry still renames'
        );
        $this->assertEquals(0, $keptCount);
        $this->assertEquals(1, $cleaner->getRenameCollisionCount());
    }

    public function testWouldDatafieldCollideWithPropertyDetectsExistingRootProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $keyName = self::PREFIX . 'city';

        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $keyName,
            'value_type' => 'string',
            'label'      => 'City',
        ], $db)->store();

        $cleaner = new CustomVariableValueCleaner($db);

        $this->assertTrue(
            $cleaner->wouldDatafieldCollideWithProperty($keyName),
            'a root property under this varname must be reported as a conflict'
        );
        $this->assertFalse(
            $cleaner->wouldDatafieldCollideWithProperty(self::PREFIX . 'no_such_property'),
            'an unrelated varname must not be reported as a conflict'
        );
    }

    public function testWouldDatafieldCollideWithPropertyIgnoresNestedProperties(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentUuid = Uuid::uuid4();
        $childKeyName = self::PREFIX . 'street';

        DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => self::PREFIX . 'address',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Address',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'parent_uuid' => $parentUuid->getBytes(),
            'key_name'    => $childKeyName,
            'value_type'  => 'string',
            'label'       => 'Street',
        ], $db)->store();

        $cleaner = new CustomVariableValueCleaner($db);

        $this->assertFalse(
            $cleaner->wouldDatafieldCollideWithProperty($childKeyName),
            'a nested property key_name is not a root varname and must not block a Data Field'
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'retype_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'shared_name_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'no_conflict_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_shared_name_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_no_conflict_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_nested_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_nested_shared_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_nested_dynamic_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_collision_host']);
            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'rename_collision_dynamic_host']);

            foreach ([self::ROOT_KEY_NAME, self::NESTED_ROOT_KEY_NAME, self::DYNAMIC_ROOT_KEY_NAME] as $rootKeyName) {
                $rows = $dba->fetchAll(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $rootKeyName)
                );
                foreach ($rows as $row) {
                    $rootUuid = DbUtil::binaryResult($row->uuid);
                    $dba->delete(
                        'director_property',
                        $dba->quoteInto('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid, $dba))
                    );
                }
                $dba->delete('director_property', $dba->quoteInto('key_name = ?', $rootKeyName));
            }
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::SHARED_KEY_NAME));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::SHARED_KEY_NAME));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::NESTED_ROOT_KEY_NAME));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::PREFIX . 'zone'));

            $addressRows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])
                    ->where('key_name = ?', self::PREFIX . 'address')
            );
            foreach ($addressRows as $row) {
                $dba->delete(
                    'director_property',
                    $dba->quoteInto('parent_uuid = ?', DbUtil::quoteBinaryCompat(
                        DbUtil::binaryResult($row->uuid),
                        $dba
                    ))
                );
            }
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'address'));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'city'));
        }

        parent::tearDown();
    }
}
