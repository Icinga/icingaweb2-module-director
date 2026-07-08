<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DeleteCustomVariableForm;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
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
                    $rowUuid = DbUtil::binaryResult($row->uuid);
                    $childUuids = array_map(
                        [DbUtil::class, 'binaryResult'],
                        $dba->fetchCol(
                            $dba->select()->from('director_property', ['uuid'])->where(
                                'parent_uuid = ?',
                                DbUtil::quoteBinaryCompat($rowUuid, $dba)
                            )
                        )
                    );
                    foreach ($childUuids as $childUuid) {
                        $dba->delete(
                            'director_property_datalist',
                            $dba->quoteInto(
                                'property_uuid = ?',
                                DbUtil::quoteBinaryCompat($childUuid, $dba)
                            )
                        );
                    }
                    $dba->delete(
                        'director_property_datalist',
                        $dba->quoteInto(
                            'property_uuid = ?',
                            DbUtil::quoteBinaryCompat($rowUuid, $dba)
                        )
                    );
                    $dba->delete(
                        'director_property',
                        $dba->quoteInto(
                            'parent_uuid = ?',
                            DbUtil::quoteBinaryCompat($rowUuid, $dba)
                        )
                    );
                    $dba->delete('director_property', $dba->quoteInto('key_name = ?', $keyName));
                }
            }

            $dba->delete('director_datalist', ['list_name = ?' => '___TEST___severity_levels']);
            $dba->delete('director_datalist', ['list_name = ?' => '___TEST___fixed_array_datalist']);
            $dba->delete('director_datafield_category', ['category_name = ?' => '___TEST___fixed_array_category']);
        }

        parent::tearDown();
    }
}
