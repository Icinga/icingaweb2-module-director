<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Form\Lib\TestableCustomVariableForm;

class CustomVariableFormTest extends BaseTestCase
{
    /** @var string[] Key names created during tests, for tearDown cleanup */
    private array $createdKeyNames = [];

    /** @var string[] Datalist names created during tests, for tearDown cleanup */
    private array $createdDatalistNames = [];

    /** @var string[] Host names created during tests, for tearDown cleanup */
    private array $createdHostNames = [];

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

    public function testChangingRootPropertyTypeRemovesStaleHostVarEntirely(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A host has "___TEST___network" set directly (not via a template Field, so
        // used_count stays 0), a fixed-dictionary with a nested "interfaces" dictionary
        // holding a "vlan" grandchild.
        $host = IcingaHost::create([
            'object_name' => '___TEST___lb01',
            'object_type' => 'object',
            'address'     => '192.0.2.50',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___lb01';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___network',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Network',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___network';

        $interfacesUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $interfacesUuid->getBytes(),
            'key_name'    => 'interfaces',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'fixed-dictionary',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'vlan',
            'parent_uuid' => $interfacesUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___network',
            'varvalue'      => json_encode(['interfaces' => ['vlan' => '10']]),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new TestableCustomVariableForm($db, $rootUuid);
        $form->setTestValues([
            'key_name'    => '___TEST___network',
            'value_type'  => 'string',
            'label'       => 'Network',
            'description' => null,
        ]);
        self::callMethod($form, 'onSuccess', []);

        $remainingChildren = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba))
        );
        $this->assertSame('0', (string) $remainingChildren, 'interfaces and vlan must be dropped from the schema');

        $hostVarRow = $dba->fetchRow(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___network')
        );
        $this->assertFalse(
            $hostVarRow,
            'the stale dictionary value must not survive retyping the root property to a string'
        );
    }

    public function testChangingNestedPropertyTypeStripsOnlyItsOwnKeyFromHostVar(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // "interfaces" is retyped away from a fixed-dictionary, discarding its "vlan"
        // grandchild. The sibling key "region" next to "interfaces" must survive untouched.
        $host = IcingaHost::create([
            'object_name' => '___TEST___lb02',
            'object_type' => 'object',
            'address'     => '192.0.2.51',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___lb02';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___network2',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Network',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___network2';

        $interfacesUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $interfacesUuid->getBytes(),
            'key_name'    => 'interfaces',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'fixed-dictionary',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'vlan',
            'parent_uuid' => $interfacesUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___network2',
            'varvalue'      => json_encode([
                'interfaces' => ['vlan' => '10'],
                'region'     => 'us-east',
            ]),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $form = new TestableCustomVariableForm($db, $interfacesUuid, true, $rootUuid);
        $form->setTestValues([
            'key_name'    => 'interfaces',
            'value_type'  => 'string',
            'label'       => null,
            'description' => null,
        ]);
        self::callMethod($form, 'onSuccess', []);

        $vlanRow = $dba->fetchRow(
            $dba->select()->from('director_property', ['uuid'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($interfacesUuid->getBytes(), $dba))
        );
        $this->assertFalse($vlanRow, 'vlan must be dropped from the schema');

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___network2')
        );
        $this->assertEquals(
            ['region' => 'us-east'],
            json_decode($updatedValue, true),
            'interfaces must be dropped from the stored value while its sibling key region survives'
        );
    }

    public function testChangingFixedArrayItemTypeClearsSlotWithoutShiftingSiblings(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // A fixed array of contacts, item 0 is a dictionary with an "ext" grandchild, item 1
        // is a plain string. Retyping item 0 to a string must clear its own slot in place
        // without renumbering or otherwise touching item 1.
        $host = IcingaHost::create([
            'object_name' => '___TEST___lb03',
            'object_type' => 'object',
            'address'     => '192.0.2.52',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___lb03';

        $arrayUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $arrayUuid->getBytes(),
            'key_name'   => '___TEST___contacts',
            'value_type' => 'fixed-array',
            'label'      => 'Contacts',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___contacts';

        $item0Uuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $item0Uuid->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $arrayUuid->getBytes(),
            'value_type'  => 'fixed-dictionary',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'ext',
            'parent_uuid' => $item0Uuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '1',
            'parent_uuid' => $arrayUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___contacts',
            'varvalue'      => json_encode([['ext' => '123'], '555-1234']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($arrayUuid->getBytes(), $dba),
        ]);

        $form = new TestableCustomVariableForm($db, $item0Uuid, true, $arrayUuid);
        $form->setTestValues([
            'key_name'    => '0',
            'value_type'  => 'string',
            'label'       => null,
            'description' => null,
        ]);
        self::callMethod($form, 'onSuccess', []);

        $item0Row = $dba->fetchRow(
            $dba->select()->from('director_property', ['key_name', 'value_type'])
                ->where('uuid = ?', DbUtil::quoteBinaryCompat($item0Uuid->getBytes(), $dba))
        );
        $this->assertNotFalse($item0Row, 'item 0 itself must survive, only its children are dropped');
        $this->assertSame('0', (string) $item0Row->key_name, 'item 0 must keep its own key_name');
        $this->assertSame('string', $item0Row->value_type, 'item 0 must carry the new value_type');

        $item1Row = $dba->fetchRow(
            $dba->select()->from('director_property', ['key_name'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($arrayUuid->getBytes(), $dba))
                ->where('key_name = ?', '1')
        );
        $this->assertNotFalse($item1Row, 'item 1 must not be renumbered or removed');

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___contacts')
        );
        $this->assertEquals(
            [null, '555-1234'],
            json_decode($updatedValue, true),
            'item 0 must be nulled out in place, item 1 must be untouched, and the value must'
            . ' stay a JSON array rather than turn into an object'
        );
    }

    public function testRenamingUsedRootPropertyUpdatesHostVarnameEvenWithoutAPropertyUuidLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => '___TEST___switch03',
            'object_type' => 'object',
            'address'     => '192.0.2.62',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___switch03';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___snmp_community',
            'value_type' => 'string',
            'label'      => 'SNMP Community',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___snmp_community';
        $this->createdKeyNames[] = '___TEST___snmp_string';

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___snmp_community',
            'varvalue'      => json_encode('public'),
            'format'        => 'json',
            'property_uuid' => null,
        ]);

        $form = new TestableCustomVariableForm($db, $rootUuid);
        self::callMethod($form, 'updateUsedCustomVarNames', ['___TEST___snmp_community', '___TEST___snmp_string']);

        $row = $dba->fetchRow(
            $dba->select()->from('icinga_host_var', ['varname'])
                ->where('host_id = ?', $host->get('id'))
        );
        $this->assertNotFalse($row);
        $this->assertSame(
            '___TEST___snmp_string',
            $row->varname,
            'the stored varname must follow the rename even though property_uuid was never linked'
        );
    }

    public function testRenamingUsedFieldUpdatesHostVarValueEvenWithoutAPropertyUuidLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => '___TEST___switch04',
            'object_type' => 'object',
            'address'     => '192.0.2.63',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___switch04';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___asset_info',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Asset Info',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___asset_info';

        $fieldUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $fieldUuid->getBytes(),
            'key_name'    => 'asset_type',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___asset_info',
            'varvalue'      => json_encode(['asset_type' => 'switch', 'location' => 'rack-12']),
            'format'        => 'json',
            'property_uuid' => null,
        ]);

        $form = new TestableCustomVariableForm($db, $fieldUuid, true, $rootUuid);
        self::callMethod($form, 'updateUsedCustomVarNames', ['asset_type', 'device_type']);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___asset_info')
        );
        $this->assertEquals(
            ['device_type' => 'switch', 'location' => 'rack-12'],
            json_decode($updatedValue, true),
            'asset_type must be renamed to device_type in the stored value even though'
            . ' property_uuid was never linked on that row'
        );
    }

    public function testRenamingUsedGrandchildFieldUpdatesNestedPathInHostVar(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        // Renaming a grandchild, two levels below the root, must reach the correct nested
        // path in the stored value rather than looking for the field name at the top level.
        $host = IcingaHost::create([
            'object_name' => '___TEST___switch05',
            'object_type' => 'object',
            'address'     => '192.0.2.64',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___switch05';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___router_config',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Router Config',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___router_config';

        $interfaceUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $interfaceUuid->getBytes(),
            'key_name'    => 'wan_interface',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'fixed-dictionary',
        ], $db)->store();

        $mtuUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $mtuUuid->getBytes(),
            'key_name'    => 'mtu',
            'parent_uuid' => $interfaceUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___router_config',
            'varvalue'      => json_encode(['wan_interface' => ['mtu' => '1500', 'speed' => '1000']]),
            'format'        => 'json',
            'property_uuid' => null,
        ]);

        $form = new TestableCustomVariableForm($db, $mtuUuid, true, $interfaceUuid);
        $form->setIsNestedField(true);
        self::callMethod($form, 'updateUsedCustomVarNames', ['mtu', 'mtu_size']);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___router_config')
        );
        $this->assertEquals(
            ['wan_interface' => ['mtu_size' => '1500', 'speed' => '1000']],
            json_decode($updatedValue, true),
            'mtu must be renamed to mtu_size at its nested path even though it is two levels'
            . ' below the root property'
        );
    }

    public function testRenamingUsedGrandchildFieldUpdatesEveryDynamicDictionaryEntry(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => '___TEST___switch06',
            'object_type' => 'object',
            'address'     => '192.0.2.65',
        ], $db);
        $host->store();
        $this->createdHostNames[] = '___TEST___switch06';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___datacenter_metrics',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Datacenter Metrics',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___datacenter_metrics';

        $cpuUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $cpuUuid->getBytes(),
            'key_name'    => 'cpu',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'fixed-dictionary',
        ], $db)->store();

        $usageUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $usageUuid->getBytes(),
            'key_name'    => 'usage_pct',
            'parent_uuid' => $cpuUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => '___TEST___datacenter_metrics',
            'varvalue'      => json_encode([
                'dc1' => ['cpu' => ['usage_pct' => '42', 'temp' => '55']],
                'dc2' => ['cpu' => ['usage_pct' => '10']],
            ]),
            'format'        => 'json',
            'property_uuid' => null,
        ]);

        $form = new TestableCustomVariableForm($db, $usageUuid, true, $cpuUuid);
        $form->setIsNestedField(true);
        self::callMethod($form, 'updateUsedCustomVarNames', ['usage_pct', 'usage_percent']);

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', '___TEST___datacenter_metrics')
        );
        $this->assertEquals(
            [
                'dc1' => ['cpu' => ['usage_percent' => '42', 'temp' => '55']],
                'dc2' => ['cpu' => ['usage_percent' => '10']],
            ],
            json_decode($updatedValue, true),
            'usage_pct must be renamed to usage_percent inside every datacenter entry, at its'
            . ' correct nested path under cpu'
        );
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();
            foreach ($this->createdHostNames as $hostName) {
                $dba->delete('icinga_host', ['object_name = ?' => $hostName]);
            }
            foreach ($this->createdKeyNames as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()
                        ->from('director_property', ['uuid'])
                        ->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $this->deletePropertyTree($dba, DbUtil::binaryResult($row->uuid));
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

    /**
     * Delete a director_property row along with all of its descendants, however deep the
     * nesting goes.
     *
     * @return void
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
