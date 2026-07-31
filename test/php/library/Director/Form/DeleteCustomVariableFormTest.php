<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DeleteCustomVariableForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class DeleteCustomVariableFormTest extends BaseTestCase
{
    public function testDeleteStringPropertyRemovesRow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => '___TEST___environment',
            'value_type' => 'string',
            'label'      => 'Environment Tag',
        ], $db);
        $property->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $property->get('uuid'),
            'key_name'    => '___TEST___environment',
            'value_type'  => 'string',
            'label'       => 'Environment Tag',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___environment')
        );
        $this->assertFalse($row, 'director_property row should be deleted');
    }

    public function testDeletePropertyWithChildrenRemovesBothRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentUuid = Uuid::uuid4();

        $parent = DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___snmp_v3',
            'value_type' => 'fixed-dictionary',
            'label'      => 'SNMPv3 Credentials',
        ], $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'parent_uuid' => $parentUuid->getBytes(),
            'key_name'    => 'auth_protocol',
            'value_type'  => 'string',
            'label'       => 'Auth Protocol',
        ], $db);
        $child->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $parent->get('uuid'),
            'key_name'    => '___TEST___snmp_v3',
            'value_type'  => 'fixed-dictionary',
            'label'       => 'SNMPv3 Credentials',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $parentRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___snmp_v3')
        );
        $this->assertFalse($parentRow, 'parent director_property row should be deleted');

        $childRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertFalse($childRow, 'child director_property row should be deleted');
    }

    public function testDeleteDatalistPropertyRemovesDatalistLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $datalist = DirectorDatalist::create([
            'list_name' => '___TEST___severity_levels',
            'owner'     => 'test',
        ], $db);
        $datalist->store();

        $property = DirectorProperty::import((object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'label'       => 'Escalation Tier',
            'description' => null,
            'parent_uuid' => null,
            'category'    => null,
            'datalist'    => '___TEST___severity_levels',
            'items'       => [],
        ], $db);
        $property->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $property->get('uuid'),
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'label'       => 'Escalation Tier',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $linkRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property_datalist', ['property_uuid'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $dba))
        );
        $this->assertFalse($linkRow, 'director_property_datalist link should be deleted');

        $propRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___escalation_tier')
        );
        $this->assertFalse($propRow, 'director_property row should be deleted');
    }

    public function testUpdateFixedArrayItemsPreservesCategoryAndDatalistLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $datalist = DirectorDatalist::create([
            'list_name' => '___TEST___fixed_array_datalist',
            'owner'     => 'test',
        ], $db);
        $datalist->store();

        $category = DirectorDatafieldCategory::create([
            'category_name' => '___TEST___fixed_array_category',
        ], $db);
        $category->store();

        $parentUuid = Uuid::uuid4();
        $parent = DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___fixed_array_parent',
            'value_type' => 'fixed-array',
            'label'      => 'Fixed Array Parent',
        ], $db);
        $parent->store();

        // Item 0 will be deleted. Item 1 is an innocent sibling that must survive the
        // reindex triggered by that deletion with its category and datalist link intact.
        $item0Uuid = Uuid::uuid4();
        $item0 = DirectorProperty::create([
            'uuid'        => $item0Uuid->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parentUuid->getBytes(),
            'value_type'  => 'string',
        ], $db);
        $item0->store();

        // Inserted directly (as the web forms do) rather than via DirectorProperty::store(),
        // so this only exercises updateFixedArrayItems(), not property creation itself.
        $item1Uuid = Uuid::uuid4();
        $dba->insert('director_property', [
            'uuid'        => DbUtil::quoteBinaryCompat($item1Uuid->getBytes(), $dba),
            'parent_uuid' => DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba),
            'key_name'    => '1',
            'value_type'  => 'datalist-strict',
            'category_id' => $category->get('id'),
            'label'       => null,
            'description' => null,
        ]);
        $dba->insert('director_property_datalist', [
            'property_uuid' => DbUtil::quoteBinaryCompat($item1Uuid->getBytes(), $dba),
            'list_uuid'     => DbUtil::quoteBinaryCompat($datalist->get('uuid'), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $item0Uuid->getBytes(),
                'key_name'    => '0',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $parentUuid->getBytes(),
            ],
            [
                'uuid'        => $parentUuid->getBytes(),
                'key_name'    => '___TEST___fixed_array_parent',
                'value_type'  => 'fixed-array',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $survivor = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid', 'category_id'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertNotFalse($survivor, 'surviving fixed-array item should still exist after the reindex');
        $this->assertEquals(
            $category->get('id'),
            (int) $survivor->category_id,
            'category_id must survive the fixed-array reindex'
        );

        $survivorUuid = DbUtil::binaryResult($survivor->uuid);
        $linkRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property_datalist', ['list_uuid'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($survivorUuid, $dba))
        );
        $this->assertNotFalse($linkRow, 'datalist link must survive the fixed-array reindex');
    }

    public function testDeletingRootFixedArrayItemUpdatesHostVarWithoutCrashing(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A host backed up nightly, with a fixed-array custom variable listing the
        // directories to include -- a realistic use of "fixed-array" in Icinga configs.
        $host = IcingaHost::create([
            'object_name' => '___TEST___backup01',
            'object_type' => 'object',
            'address'     => '192.0.2.10',
        ], $db);
        $host->store();

        $parentUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___backup_directories',
            'value_type' => 'fixed-array',
            'label'      => 'Backup Directories',
        ], $db)->store();

        foreach (['0', '1', '2'] as $keyName) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $keyName,
                'parent_uuid' => $parentUuid->getBytes(),
                'value_type'  => 'string',
            ], $db)->store();
        }

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___backup_directories',
            'varvalue'      => json_encode(['/etc', '/var/www', '/home']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba),
        ]);

        // Delete the '/etc' entry (array index 0).
        $item0Uuid = DbUtil::binaryResult($dba->fetchOne(
            $dba->select()->from('director_property', ['uuid'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
                ->where('key_name = ?', '0')
        ));

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $item0Uuid,
                'key_name'    => '0',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $parentUuid->getBytes(),
            ],
            [
                'uuid'        => $parentUuid->getBytes(),
                'key_name'    => '___TEST___backup_directories',
                'value_type'  => 'fixed-array',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___backup_directories')
        );

        $this->assertEquals(
            ['/var/www', '/home'],
            json_decode($updatedValue, true),
            'the remaining directories must be reindexed as a bare list, not wrapped under the array\'s own key name'
        );
    }

    public function testUpdateFixedArrayItemsReindexesNumericallyNotLexicographically(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A fixed array of 11 on-call escalation contacts (indices '0'..'10'): with more than
        // 10 items, a lexicographic sort of key_name puts '10' right after '1', before '2'.
        $parentUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___escalation_contacts',
            'value_type' => 'fixed-array',
            'label'      => 'Escalation Contacts',
        ], $db)->store();

        for ($i = 0; $i <= 10; $i++) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => (string) $i,
                'parent_uuid' => $parentUuid->getBytes(),
                'value_type'  => 'string',
                'label'       => "Contact $i",
            ], $db)->store();
        }

        // Delete contact '5'; the remaining 10 contacts must be reindexed to '0'..'9' in their
        // original numeric order (Contact 0,1,2,3,4,6,7,8,9,10 -> key_name 0,1,2,3,4,5,6,7,8,9).
        $deletedUuid = DbUtil::binaryResult($dba->fetchOne(
            $dba->select()->from('director_property', ['uuid'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
                ->where('key_name = ?', '5')
        ));

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $deletedUuid,
                'key_name'    => '5',
                'value_type'  => 'string',
                'label'       => 'Contact 5',
                'description' => null,
                'parent_uuid' => $parentUuid->getBytes(),
            ],
            [
                'uuid'        => $parentUuid->getBytes(),
                'key_name'    => '___TEST___escalation_contacts',
                'value_type'  => 'fixed-array',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $rows = $dba->fetchAll(
            $dba->select()->from('director_property', ['key_name', 'label'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $labelsByKeyName = [];
        foreach ($rows as $row) {
            $labelsByKeyName[$row->key_name] = $row->label;
        }

        // Numeric order (0,1,2,3,4,6,7,8,9,10 -> new keys 0..9) puts "Contact 2" at key_name '2'
        // and "Contact 10" at key_name '9'. A lexicographic sort ('0','1','10','2',...) would
        // instead put "Contact 10" at key_name '2' and "Contact 2" at key_name '3'.
        $this->assertEquals(
            'Contact 2',
            $labelsByKeyName['2'] ?? null,
            'key_name 2 must still refer to the item originally labeled "Contact 2"'
        );
        $this->assertEquals(
            'Contact 10',
            $labelsByKeyName['9'] ?? null,
            'the item originally labeled "Contact 10" must end up last (key_name 9), not'
            . ' misplaced by a lexicographic sort'
        );
    }

    public function testOnSuccessRollsBackTransactionWhenAnExceptionIsThrown(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A host maintenance window dictionary with one field ("reason"); an unrelated failure
        // partway through processing must not leave the transaction dangling.
        $parentUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___maintenance_window',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Maintenance Window',
        ], $db)->store();

        $childUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $childUuid->getBytes(),
            'key_name'    => 'reason',
            'parent_uuid' => $parentUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $host = IcingaHost::create([
            'object_name' => '___TEST___maintenance01',
            'object_type' => 'object',
            'address'     => '192.0.2.12',
        ], $db);
        $host->store();

        // A malformed stored value (JSON literal null, decoding to PHP null) makes
        // removeDictionaryItem() receive null where an array is required, throwing a
        // TypeError partway through onSuccess() -- simulating any unexpected mid-transaction
        // failure.
        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___maintenance_window',
            'varvalue'      => 'null',
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $childUuid->getBytes(),
                'key_name'    => 'reason',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $parentUuid->getBytes(),
            ],
            [
                'uuid'        => $parentUuid->getBytes(),
                'key_name'    => '___TEST___maintenance_window',
                'value_type'  => 'fixed-dictionary',
                'parent_uuid' => null,
            ]
        );

        try {
            self::callMethod($form, 'onSuccess', []);
            $this->fail('Expected an exception while processing the malformed stored value');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertFalse(
            $dba->getConnection()->inTransaction(),
            'onSuccess() must roll back its transaction when interrupted by an exception'
        );
    }

    public function testDeletingRootPropertyRemovesHostVarEvenWithoutAPropertyUuidLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => '___TEST___switch02',
            'object_type' => 'object',
            'address'     => '192.0.2.61',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___snmp_community',
            'value_type' => 'string',
            'label'      => 'SNMP Community',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___snmp_community',
            'varvalue'      => json_encode('public'),
            'format'        => 'json',
            'property_uuid' => null,
        ]);

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $rootUuid->getBytes(),
            'key_name'    => '___TEST___snmp_community',
            'value_type'  => 'string',
            'label'       => 'SNMP Community',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $row = $dba->fetchRow(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___snmp_community')
        );
        $this->assertFalse($row, 'the host_var row must be dropped by varname even without a property_uuid link');
    }

    public function testDeletingDynamicDictionaryFieldUpdatesEveryEntryInHostVar(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A host's per-datacenter settings: a dynamic dictionary keyed by datacenter name,
        // each entry holding a contact_email and timezone. Deleting contact_email must
        // strip it from every datacenter's entry, even when the entry doesn't become empty.
        $host = IcingaHost::create([
            'object_name' => '___TEST___multidc01',
            'object_type' => 'object',
            'address'     => '192.0.2.40',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___datacenter_settings',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Datacenter Settings',
        ], $db)->store();

        $contactEmailUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $contactEmailUuid->getBytes(),
            'key_name'    => 'contact_email',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___datacenter_settings',
            'varvalue'      => json_encode([
                'dc1' => ['contact_email' => 'dc1@example.com', 'timezone' => 'UTC'],
                'dc2' => ['contact_email' => 'dc2@example.com', 'timezone' => 'PST'],
            ]),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $contactEmailUuid->getBytes(),
                'key_name'    => 'contact_email',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___datacenter_settings',
                'value_type'  => 'dynamic-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___datacenter_settings')
        );

        $this->assertEquals(
            [
                'dc1' => ['timezone' => 'UTC'],
                'dc2' => ['timezone' => 'PST'],
            ],
            json_decode($updatedValue, true),
            'contact_email must be removed from every datacenter entry, not just entries'
            . ' that become fully empty as a result'
        );
    }

    public function testDeletingDynamicDictionaryFieldConvertsEmptiedEntryToEmptyObjectInHostVar(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // Same datacenter-settings dictionary, but dc2 has only the field being deleted --
        // its entry must become an empty object (still a dictionary), not be dropped or
        // turned into an empty list.
        $host = IcingaHost::create([
            'object_name' => '___TEST___multidc02',
            'object_type' => 'object',
            'address'     => '192.0.2.41',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___datacenter_settings_2',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Datacenter Settings 2',
        ], $db)->store();

        $contactEmailUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $contactEmailUuid->getBytes(),
            'key_name'    => 'contact_email',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___datacenter_settings_2',
            'varvalue'      => json_encode([
                'dc1' => ['contact_email' => 'dc1@example.com', 'timezone' => 'UTC'],
                'dc2' => ['contact_email' => 'dc2@example.com'],
            ]),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $contactEmailUuid->getBytes(),
                'key_name'    => 'contact_email',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___datacenter_settings_2',
                'value_type'  => 'dynamic-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___datacenter_settings_2')
        );

        $this->assertEquals(
            '{"dc1":{"timezone":"UTC"},"dc2":{}}',
            $updatedValue,
            'an entry emptied by the deletion must be encoded as an empty object, not dropped'
            . ' or turned into an empty list'
        );
    }

    public function testDeletingDynamicDictionaryFieldUpdatesEveryEntryInOverrideServiceVars(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // The same per-datacenter settings dictionary, but overridden for a specific service
        // via _override_servicevars, with one datacenter entry fully emptied by the deletion.
        $host = IcingaHost::create([
            'object_name' => '___TEST___multidc03',
            'object_type' => 'object',
            'address'     => '192.0.2.42',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___datacenter_settings_3',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Datacenter Settings 3',
        ], $db)->store();

        $contactEmailUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $contactEmailUuid->getBytes(),
            'key_name'    => 'contact_email',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'  => $host->get('id'),
            'varname'  => '_override_servicevars',
            'varvalue' => json_encode([
                'web_check' => [
                    '___TEST___datacenter_settings_3' => [
                        'dc1' => ['contact_email' => 'dc1@example.com', 'timezone' => 'UTC'],
                        'dc2' => ['contact_email' => 'dc2@example.com'],
                    ],
                ],
            ]),
            'format'   => 'json',
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $contactEmailUuid->getBytes(),
                'key_name'    => 'contact_email',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___datacenter_settings_3',
                'value_type'  => 'dynamic-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '_override_servicevars')
        );

        $this->assertEquals(
            [
                'web_check' => [
                    '___TEST___datacenter_settings_3' => [
                        'dc1' => ['timezone' => 'UTC'],
                    ],
                ],
            ],
            json_decode($updatedValue, true),
            'contact_email must be stripped from every datacenter entry, and an entry fully'
            . ' emptied by the deletion must be dropped from the override'
        );
    }

    public function testDeletingFieldUpdatesServiceVarWithoutCrashing(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // Existing coverage for removeObjectCustomVars() only ever touches
        // icinga_host_var. The cleaner walks host, service, notification, command,
        // user and service_set tables the same way, so a service-attached field
        // needs cleanup too.
        $service = IcingaService::create([
            'object_name'  => '___TEST___load-check',
            'object_type'  => 'template',
            'display_name' => 'Load Check',
        ], $db);
        $service->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___load_thresholds',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Load Thresholds',
        ], $db)->store();

        $warnUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $warnUuid->getBytes(),
            'key_name'    => 'warn',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_service_var', [
            'service_id'    => $service->get('id'),
            'varname'       => '___TEST___load_thresholds',
            'varvalue'      => json_encode(['warn' => '5', 'crit' => '10']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $warnUuid->getBytes(),
                'key_name'    => 'warn',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___load_thresholds',
                'value_type'  => 'fixed-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_service_var', ['varvalue'])
                ->where('service_id = ?', $service->get('id'))
                ->where('varname = ?', '___TEST___load_thresholds')
        );

        $this->assertEquals(
            ['crit' => '10'],
            json_decode($updatedValue, true),
            'warn must be removed from the service-attached fixed-dictionary value'
        );

        $service->delete();
        $dba->delete('director_property', ['key_name = ?' => '___TEST___load_thresholds']);
    }

    public function testDeletingFieldUpdatesServiceSetVarWithoutCrashing(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $serviceSet = IcingaServiceSet::create([
            'object_name' => '___TEST___load-checks',
            'object_type' => 'template',
        ], $db);
        $serviceSet->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___load_thresholds_set',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Load Thresholds',
        ], $db)->store();

        $warnUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $warnUuid->getBytes(),
            'key_name'    => 'warn',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_service_set_var', [
            'service_set_id' => $serviceSet->get('id'),
            'varname'        => '___TEST___load_thresholds_set',
            'varvalue'       => json_encode(['warn' => '5', 'crit' => '10']),
            'format'         => 'json',
            'property_uuid'  => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $warnUuid->getBytes(),
                'key_name'    => 'warn',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___load_thresholds_set',
                'value_type'  => 'fixed-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_service_set_var', ['varvalue'])
                ->where('service_set_id = ?', $serviceSet->get('id'))
                ->where('varname = ?', '___TEST___load_thresholds_set')
        );

        $this->assertEquals(
            ['crit' => '10'],
            json_decode($updatedValue, true),
            'warn must be removed from the service-set-attached fixed-dictionary value'
        );

        $serviceSet->delete();
        $dba->delete('director_property', ['key_name = ?' => '___TEST___load_thresholds_set']);
    }

    public function testFixedArrayReindexPreservesRequiredFlagOnSurvivingItem(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A fixed array of on-call contact numbers, defined on a host template. The second
        // contact has been marked "required" directly via icinga_host_property. Deleting the
        // first contact forces this survivor to be renumbered from key_name '1' to '0' -- the
        // renumbering must not lose the "required" binding along the way.
        $host = IcingaHost::create([
            'object_name' => '___TEST___oncall_template',
            'object_type' => 'template',
        ], $db);
        $host->store();

        $parentUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___contact_numbers',
            'value_type' => 'fixed-array',
            'label'      => 'Contact Numbers',
        ], $db)->store();

        $item0Uuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $item0Uuid->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $parentUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $item1Uuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $item1Uuid->getBytes(),
            'key_name'    => '1',
            'parent_uuid' => $parentUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_property', [
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat($item1Uuid->getBytes(), $dba),
            'required'      => 'y',
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $item0Uuid->getBytes(),
                'key_name'    => '0',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $parentUuid->getBytes(),
            ],
            [
                'uuid'        => $parentUuid->getBytes(),
                'key_name'    => '___TEST___contact_numbers',
                'value_type'  => 'fixed-array',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $survivor = $dba->fetchRow(
            $dba->select()->from('director_property', ['uuid', 'key_name'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertNotFalse($survivor, 'the surviving item must still exist after the reindex');
        $this->assertEquals('0', $survivor->key_name, 'the survivor must be renumbered to key_name 0');

        $survivorUuid = DbUtil::binaryResult($survivor->uuid);
        $requiredRow = $dba->fetchRow(
            $dba->select()->from('icinga_host_property', ['required'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($survivorUuid, $dba))
        );

        $this->assertNotFalse(
            $requiredRow,
            'the required binding on the surviving item must not be lost by the reindex'
        );
    }

    public function testDeletingNestedPropertyDetachesRootVarnameNotChildKeyName(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A switch's SNMP settings dictionary, with a legacy Data Field still claiming the
        // root varname -- this is what makes onSuccess() keep the stored value alive and
        // detach its property_uuid instead of deleting the property outright.
        DirectorDatafield::create([
            'varname'  => '___TEST___switch_snmp',
            'caption'  => 'Switch SNMP',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___switch_snmp',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Switch SNMP',
        ], $db)->store();

        $communityUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $communityUuid->getBytes(),
            'key_name'    => 'community',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $switchHost = IcingaHost::create([
            'object_name' => '___TEST___switch01',
            'object_type' => 'object',
            'address'     => '192.0.2.62',
        ], $db);
        $switchHost->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $switchHost->get('id'),
            'varname'       => '___TEST___switch_snmp',
            'varvalue'      => json_encode(['community' => 'public']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        // An unrelated top-level property that only happens to share its key_name
        // ("community") with the nested field being deleted. The bug used the nested
        // property's own key_name to detach stored values instead of the colliding root's,
        // which would have nulled this row's property_uuid too.
        $unrelatedUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $unrelatedUuid->getBytes(),
            'key_name'   => 'community',
            'value_type' => 'string',
            'label'      => 'Community',
        ], $db)->store();

        $printerHost = IcingaHost::create([
            'object_name' => '___TEST___printer01',
            'object_type' => 'object',
            'address'     => '192.0.2.63',
        ], $db);
        $printerHost->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $printerHost->get('id'),
            'varname'       => 'community',
            'varvalue'      => json_encode('printer-guild'),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($unrelatedUuid->getBytes(), $dba),
        ]);

        $form = new DeleteCustomVariableForm(
            $db,
            [
                'uuid'        => $communityUuid->getBytes(),
                'key_name'    => 'community',
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
                'parent_uuid' => $rootUuid->getBytes(),
            ],
            [
                'uuid'        => $rootUuid->getBytes(),
                'key_name'    => '___TEST___switch_snmp',
                'value_type'  => 'fixed-dictionary',
                'parent_uuid' => null,
            ]
        );

        self::callMethod($form, 'onSuccess', []);

        $unrelatedRow = $dba->fetchRow(
            $dba->select()->from('icinga_host_var', ['property_uuid'])
                ->where('host_id = ?', $printerHost->get('id'))
                ->where('varname = ?', 'community')
        );
        $this->assertNotFalse($unrelatedRow, 'the unrelated "community" var row must not be deleted');
        $this->assertEquals(
            $unrelatedUuid->getBytes(),
            DbUtil::binaryResult($unrelatedRow->property_uuid),
            'detaching the colliding root property must not touch an unrelated var row that only'
            . ' shares the deleted nested field\'s own key_name'
        );

        $rootRow = $dba->fetchRow(
            $dba->select()->from('icinga_host_var', ['property_uuid'])
                ->where('host_id = ?', $switchHost->get('id'))
                ->where('varname = ?', '___TEST___switch_snmp')
        );
        $this->assertNotFalse($rootRow, 'the root var row must not be deleted, only detached');
        $this->assertNull(
            $rootRow->property_uuid,
            'the root var row must have its property_uuid nulled, since it is kept alive by the'
            . ' colliding legacy Data Field'
        );

        $dba->delete('icinga_host', ['object_name = ?' => '___TEST___switch01']);
        $dba->delete('icinga_host', ['object_name = ?' => '___TEST___printer01']);
        $dba->delete('director_property', ['key_name = ?' => 'community']);
        $dba->delete('director_property', ['key_name = ?' => '___TEST___switch_snmp']);
        $dba->delete('director_datafield', ['varname = ?' => '___TEST___switch_snmp']);
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            // A test exercising the no-rollback bug can leave a transaction open if the fix
            // under test regresses; clear it first so cleanup below (and later tests) aren't
            // run inside a stale, never-committed transaction.
            if ($dba->getConnection()->inTransaction()) {
                $dba->getConnection()->rollBack();
            }
            // Delete hosts (cascades to icinga_host_var) before director_property rows below —
            // icinga_host_var.property_uuid has no ON DELETE CASCADE to director_property.
            $hostNames = [
                '___TEST___backup01',
                '___TEST___maintenance01',
                '___TEST___multidc01',
                '___TEST___multidc02',
                '___TEST___multidc03',
                '___TEST___oncall_template',
                '___TEST___switch02',
            ];
            foreach ($hostNames as $hostName) {
                $dba->delete('icinga_host', ['object_name = ?' => $hostName]);
            }
            $keyNames = [
                '___TEST___environment',
                '___TEST___snmp_v3',
                '___TEST___escalation_tier',
                '___TEST___fixed_array_parent',
                '___TEST___backup_directories',
                '___TEST___escalation_contacts',
                '___TEST___maintenance_window',
                '___TEST___datacenter_settings',
                '___TEST___datacenter_settings_2',
                '___TEST___datacenter_settings_3',
                '___TEST___contact_numbers',
                '___TEST___snmp_community',
            ];
            foreach ($keyNames as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()
                        ->from('director_property', ['uuid'])
                        ->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    // Read the (possibly stream-backed, on PostgreSQL) uuid column exactly once —
                    // a PHP stream resource can only be consumed a single time.
                    $this->deletePropertyTree($dba, DbUtil::binaryResult($row->uuid));
                }
            }

            $dba->delete('director_datalist', ['list_name = ?' => '___TEST___severity_levels']);
            $dba->delete('director_datalist', ['list_name = ?' => '___TEST___fixed_array_datalist']);
            $dba->delete('director_datafield_category', ['category_name = ?' => '___TEST___fixed_array_category']);
        }

        parent::tearDown();
    }

    /**
     * Delete a director_property row along with all of its descendants, however deep the
     * nesting goes, cleaning up their director_property_datalist links as we go.
     */
    private function deletePropertyTree($dba, string $uuid): void
    {
        $childUuids = array_map(
            [DbUtil::class, 'binaryResult'],
            $dba->fetchCol(
                $dba->select()->from('director_property', ['uuid'])->where(
                    'parent_uuid = ?',
                    DbUtil::quoteBinaryCompat($uuid, $dba)
                )
            )
        );
        foreach ($childUuids as $childUuid) {
            $this->deletePropertyTree($dba, $childUuid);
        }
        $dba->delete(
            'director_property_datalist',
            $dba->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba))
        );
        $dba->delete(
            'director_property',
            $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba))
        );
    }
}
