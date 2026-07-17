<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Form\Lib\TestableCustomVariableForm;

class CustomVariableFormTest extends BaseTestCase
{
    /** @var string[] Key names created during tests, for tearDown cleanup */
    private array $createdKeyNames = [];

    /** @var string[] Datalist names created during tests, for tearDown cleanup */
    private array $createdDatalistNames = [];

    public function testAddStringPropertyCreatesRow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $form = new TestableCustomVariableForm($db);
        $form->setTestValues([
            'key_name'    => '___TEST___environment',
            'value_type'  => 'string',
            'label'       => 'Environment Tag',
            'description' => 'Deployment environment: production, staging, or dev',
        ]);
        $this->createdKeyNames[] = '___TEST___environment';

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name', 'value_type'])
                ->where('key_name = ?', '___TEST___environment')
        );

        $this->assertNotFalse($row, 'director_property row should be created');
        $this->assertSame('string', $row->value_type);
        $this->assertNotNull($form->getUUid(), 'form UUID should be set after creation');
    }

    public function testAddDynamicArrayPropertyCreatesParentAndChildRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $form = new TestableCustomVariableForm($db);
        $form->setTestValues([
            'key_name'    => '___TEST___contact_groups',
            'value_type'  => 'dynamic-array',
            'item_type'   => 'string',
            'label'       => 'Contact Groups',
            'description' => 'Teams that receive alerts for this host (e.g. noc, linux-ops)',
        ]);
        $this->createdKeyNames[] = '___TEST___contact_groups';

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $parentRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['value_type'])
                ->where('key_name = ?', '___TEST___contact_groups')
        );
        $this->assertNotFalse($parentRow, 'parent director_property row should be created');
        $this->assertSame('dynamic-array', $parentRow->value_type);

        $parentUuid = $form->getUUid();
        $this->assertNotNull($parentUuid, 'form UUID should be set after creation');
        $childRows = $dba->fetchAll(
            $dba->select()
                ->from('director_property', ['key_name', 'value_type'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertCount(1, $childRows, 'exactly one child row should be created for the item type');
        $this->assertSame('0', (string) $childRows[0]->key_name);
        $this->assertSame('string', $childRows[0]->value_type);
    }

    public function testUpdateStringPropertyKeyName(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $createForm = new TestableCustomVariableForm($db);
        $createForm->setTestValues([
            'key_name'    => '___TEST___http_uri',
            'value_type'  => 'string',
            'label'       => 'HTTP URI',
            'description' => 'URI path to probe, e.g. /api/health',
        ]);
        $this->createdKeyNames[] = '___TEST___http_uri';
        $this->createdKeyNames[] = '___TEST___http_url';
        self::callMethod($createForm, 'onSuccess', []);
        $uuid = $createForm->getUUid();

        $updateForm = new TestableCustomVariableForm($db, $uuid);
        $updateForm->setTestValues([
            'key_name'    => '___TEST___http_url',
            'value_type'  => 'string',
            'label'       => 'HTTP URL',
            'description' => 'URI path to probe, e.g. /api/health',
        ]);
        self::callMethod($updateForm, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $renamedRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name'])
                ->where('key_name = ?', '___TEST___http_url')
        );
        $this->assertNotFalse($renamedRow, 'renamed director_property row should exist');

        $oldRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name'])
                ->where('key_name = ?', '___TEST___http_uri')
        );
        $this->assertFalse($oldRow, 'original key_name should not exist after rename');
    }

    public function testFailedCreateDoesNotLeaveTransactionOpen(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $form = new TestableCustomVariableForm($db);
        $form->setTestValues([
            'key_name'    => '___TEST___ssh_port',
            'value_type'  => 'number',
            'label'       => 'SSH Port',
            'description' => 'TCP port the SSH daemon listens on',
        ]);
        $this->createdKeyNames[] = '___TEST___ssh_port';
        self::callMethod($form, 'onSuccess', []);

        // A second admin, unaware the property already exists, tries to create the same one.
        $secondForm = new TestableCustomVariableForm($db);
        $secondForm->setTestValues([
            'key_name'    => '___TEST___ssh_port',
            'value_type'  => 'number',
            'label'       => 'SSH Port (custom)',
            'description' => 'Port used for SSH health checks',
        ]);

        $thrown = false;
        try {
            self::callMethod($secondForm, 'onSuccess', []);
        } catch (\Throwable $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'creating a second property with the same key_name must raise an exception');

        // The transaction opened by the failed onSuccess() call must not be left open. On
        // PostgreSQL, an unhandled exception inside beginTransaction() without a matching
        // rollBack() aborts the whole connection, so the very next query on it fails with
        // "current transaction is aborted" instead of running normally.
        $count = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('key_name = ?', '___TEST___ssh_port')
        );
        $this->assertSame('1', (string) $count, 'exactly one row must exist, the failed insert must not linger');
    }

    public function testUpdateDatalistPropertyRelinksToNewlySelectedDatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $listA = DirectorDatalist::create([
            'list_name' => '___TEST___tier_a',
            'owner'     => 'test',
        ], $db);
        $listA->store();
        $this->createdDatalistNames[] = '___TEST___tier_a';

        $listB = DirectorDatalist::create([
            'list_name' => '___TEST___tier_b',
            'owner'     => 'test',
        ], $db);
        $listB->store();
        $this->createdDatalistNames[] = '___TEST___tier_b';

        $createForm = new TestableCustomVariableForm($db);
        $createForm->setTestValues([
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'item_type'   => '',
            'list'        => $listA->get('id'),
            'label'       => 'Escalation Tier',
            'description' => 'Severity tier used to page the on-call rotation',
        ]);
        $this->createdKeyNames[] = '___TEST___escalation_tier';
        self::callMethod($createForm, 'onSuccess', []);
        $uuid = $createForm->getUUid();

        $linkedListUuid = $dba->fetchOne(
            $dba->select()
                ->from('director_property_datalist', ['list_uuid'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($uuid->getBytes(), $dba))
        );
        $this->assertSame(
            DbUtil::binaryResult($listA->get('uuid')),
            DbUtil::binaryResult($linkedListUuid),
            'property should initially be linked to the datalist selected on creation'
        );

        $updateForm = new TestableCustomVariableForm($db, $uuid);
        $updateForm->setTestValues([
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'item_type'   => '',
            'list'        => $listB->get('id'),
            'label'       => 'Escalation Tier',
            'description' => 'Severity tier used to page the on-call rotation',
        ]);
        self::callMethod($updateForm, 'onSuccess', []);

        $relinkedListUuid = $dba->fetchOne(
            $dba->select()
                ->from('director_property_datalist', ['list_uuid'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($uuid->getBytes(), $dba))
        );
        $this->assertSame(
            DbUtil::binaryResult($listB->get('uuid')),
            DbUtil::binaryResult($relinkedListUuid),
            'property must be relinked to the newly selected datalist even though value_type did not change'
        );
    }

    public function testUpdateDatalistPropertyChangesItemType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $list = DirectorDatalist::create([
            'list_name' => '___TEST___regions',
            'owner'     => 'test',
        ], $db);
        $list->store();
        $this->createdDatalistNames[] = '___TEST___regions';

        $createForm = new TestableCustomVariableForm($db);
        $createForm->setTestValues([
            'key_name'    => '___TEST___allowed_regions',
            'value_type'  => 'datalist-non-strict',
            'item_type'   => 'string',
            'list'        => $list->get('id'),
            'label'       => 'Allowed Regions',
            'description' => 'Regions this host is permitted to be deployed in',
        ]);
        $this->createdKeyNames[] = '___TEST___allowed_regions';
        self::callMethod($createForm, 'onSuccess', []);
        $uuid = $createForm->getUUid();

        $updateForm = new TestableCustomVariableForm($db, $uuid);
        $updateForm->setTestValues([
            'key_name'    => '___TEST___allowed_regions',
            'value_type'  => 'datalist-non-strict',
            'item_type'   => 'dynamic-array',
            'list'        => $list->get('id'),
            'label'       => 'Allowed Regions',
            'description' => 'Regions this host is permitted to be deployed in',
        ]);
        self::callMethod($updateForm, 'onSuccess', []);

        $childValueType = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['value_type'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($uuid->getBytes(), $dba))
                ->where('key_name = ?', '0')
        );
        $this->assertSame(
            'dynamic-array',
            $childValueType,
            'item type must be updated even though the datalist property type itself did not change'
        );
    }

    public function testUpdateDynamicArrayPropertyChangesItemType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $createForm = new TestableCustomVariableForm($db);
        $createForm->setTestValues([
            'key_name'    => '___TEST___backup_ports',
            'value_type'  => 'dynamic-array',
            'item_type'   => 'string',
            'label'       => 'Backup Ports',
            'description' => 'TCP ports used by the backup agent',
        ]);
        $this->createdKeyNames[] = '___TEST___backup_ports';
        self::callMethod($createForm, 'onSuccess', []);
        $uuid = $createForm->getUUid();

        $updateForm = new TestableCustomVariableForm($db, $uuid);
        $updateForm->setTestValues([
            'key_name'    => '___TEST___backup_ports',
            'value_type'  => 'dynamic-array',
            'item_type'   => 'number',
            'label'       => 'Backup Ports',
            'description' => 'TCP ports used by the backup agent',
        ]);
        self::callMethod($updateForm, 'onSuccess', []);

        $childValueType = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['value_type'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($uuid->getBytes(), $dba))
                ->where('key_name = ?', '0')
        );
        $this->assertSame(
            'number',
            $childValueType,
            'item type must be updated even though the dynamic-array property type itself did not change'
        );
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();
            foreach ($this->createdKeyNames as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()
                        ->from('director_property', ['uuid'])
                        ->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $dba->delete(
                        'director_property',
                        $dba->quoteInto(
                            'parent_uuid = ?',
                            DbUtil::quoteBinaryCompat(DbUtil::binaryResult($row->uuid), $dba)
                        )
                    );
                    $dba->delete('director_property', $dba->quoteInto('key_name = ?', $keyName));
                }
            }

            foreach ($this->createdDatalistNames as $listName) {
                if (DirectorDatalist::exists($listName, $db)) {
                    DirectorDatalist::load($listName, $db)->delete();
                }
            }
        }

        parent::tearDown();
    }
}
