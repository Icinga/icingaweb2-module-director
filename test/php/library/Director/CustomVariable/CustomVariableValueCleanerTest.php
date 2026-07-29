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

            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', self::ROOT_KEY_NAME)
            );
            foreach ($rows as $row) {
                $rootUuid = DbUtil::binaryResult($row->uuid);
                $dba->delete(
                    'director_property',
                    $dba->quoteInto('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid, $dba))
                );
            }
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::ROOT_KEY_NAME));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::SHARED_KEY_NAME));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::SHARED_KEY_NAME));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::PREFIX . 'zone'));
        }

        parent::tearDown();
    }
}
