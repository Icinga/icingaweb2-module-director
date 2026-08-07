<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\CustomVariableForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\Icinga\Module\Director\Form\Lib\TestableCustomVariableForm;

class CustomVariableFormTest extends BaseTestCase
{
    /** @var string[] Key names created during tests, for tearDown cleanup */
    private array $createdKeyNames = [];

    /** @var string[] Datalist names created during tests, for tearDown cleanup */
    private array $createdDatalistNames = [];

    /** @var string[] Host names created during tests, for tearDown cleanup */
    private array $createdHostNames = [];

    /** @var string[] Data Field varnames created during tests, for tearDown cleanup */
    private array $createdDatafieldNames = [];

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

    public function testAssembleOffersContainerTypesOnlyAtTheTopLevel(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // The form's persistence bypasses DirectorProperty::store() (see addNewProperty()/
        // updateExistingProperty(), both raw $db->insert()/update()), so this dropdown is the
        // only thing standing between a submitted request and an illegal nested container row.
        // Zend's DeferredInArrayValidator on the select rejects any value_type not present in
        // these options, so what's offered here is a real enforcement boundary, not cosmetic.
        $db = $this->getDb();

        $rootForm = new CustomVariableForm($db);
        self::callMethod($rootForm, 'assemble', []);
        $rootOptions = $rootForm->getElement('value_type');
        $this->assertNotNull($rootOptions->getOption('fixed-array'));
        $this->assertNotNull($rootOptions->getOption('fixed-dictionary'));
        $this->assertNotNull($rootOptions->getOption('dynamic-dictionary'));
        $this->assertNotNull($rootOptions->getOption('dynamic-array'));

        $fieldForm = new CustomVariableForm($db, null, true, Uuid::uuid4());
        self::callMethod($fieldForm, 'assemble', []);
        $fieldOptions = $fieldForm->getElement('value_type');
        $this->assertNull(
            $fieldOptions->getOption('fixed-array'),
            'fixed-array must not be offered as a nested field'
        );
        $this->assertNull(
            $fieldOptions->getOption('fixed-dictionary'),
            'fixed-dictionary must not be offered as a nested field'
        );
        $this->assertNull(
            $fieldOptions->getOption('dynamic-dictionary'),
            'dynamic-dictionary must not be offered as a nested field'
        );
        $this->assertNotNull(
            $fieldOptions->getOption('dynamic-array'),
            'dynamic-array must still be offered as a nested field'
        );
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

    public function testUsedPropertyCannotHaveItsValueTypeChangedViaCraftedSubmission(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = $this->createHost('___TEST___router01', '192.0.2.70');

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___locked_type',
            'value_type' => 'string',
            'label'      => 'Locked Type',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___locked_type';
        $this->attachPropertyToHost($rootUuid, $host, $dba);

        // value_type is disabled in the UI once a property is used, but disabled is just
        // a browser hint, so a crafted request could still submit a different one here.
        $form = new TestableCustomVariableForm($db, $rootUuid);

        self::callMethod($form, 'updateExistingProperty', [
            [
                'key_name'    => '___TEST___locked_type',
                'value_type'  => 'number',
                'label'       => 'Locked Type',
                'description' => null,
            ]
        ]);

        $storedType = $dba->fetchOne(
            $dba->select()->from('director_property', ['value_type'])
                ->where('key_name = ?', '___TEST___locked_type')
        );

        $this->assertSame(
            'string',
            $storedType,
            'value_type must stay put for a used property even if a different one is submitted'
        );
    }

    public function testPropertyAttachedAfterFormWasLoadedIsStillTreatedAsUsed(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___link_speed',
            'value_type' => 'string',
            'label'      => 'Link Speed',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___link_speed';

        // The edit page was loaded while the property was still unused, so its hidden
        // used_count field would have said 0. A second admin attaches it to a host
        // before this form gets submitted.
        $form = new TestableCustomVariableForm($db, $rootUuid);

        $host = $this->createHost('___TEST___switch05', '192.0.2.64');
        $this->attachPropertyToHost($rootUuid, $host, $dba);

        self::callMethod($form, 'updateExistingProperty', [
            [
                'key_name'    => '___TEST___link_speed',
                'value_type'  => 'number',
                'label'       => 'Link Speed',
                'description' => null,
            ]
        ]);

        $storedType = $dba->fetchOne(
            $dba->select()->from('director_property', ['value_type'])
                ->where('key_name = ?', '___TEST___link_speed')
        );

        $this->assertSame(
            'string',
            $storedType,
            'a property attached after the form was loaded must still be treated as used'
        );
    }

    public function testUsedFieldStaysProtectedWhenOnlyItsRootPropertyIsAttached(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = $this->createHost('___TEST___switch06', '192.0.2.65');

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___asset_info2',
            'value_type' => 'fixed-dictionary',
            'label'      => 'Asset Info',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___asset_info2';

        $fieldUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $fieldUuid->getBytes(),
            'key_name'    => 'serial_number',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        // Only the root property is attached, a field never gets its own attachment
        // row, so the fix has to walk up to the root to see that this is used.
        $this->attachPropertyToHost($rootUuid, $host, $dba);

        $form = new TestableCustomVariableForm($db, $fieldUuid, true, $rootUuid);

        self::callMethod($form, 'updateExistingProperty', [
            [
                'key_name'    => 'serial_number',
                'value_type'  => 'number',
                'label'       => null,
                'description' => null,
            ]
        ]);

        $storedType = $dba->fetchOne(
            $dba->select()->from('director_property', ['value_type'])
                ->where('uuid = ?', DbUtil::quoteBinaryCompat($fieldUuid->getBytes(), $dba))
        );

        $this->assertSame(
            'string',
            $storedType,
            'a field must count as used when its root property is attached'
        );
    }

    public function testRetypeIsBlockedWhenALegacyDatafieldStillOwnsTheName(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        DirectorDatafield::create([
            'varname'  => '___TEST___shared_tag',
            'caption'  => 'Shared Tag',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();
        $this->createdDatafieldNames[] = '___TEST___shared_tag';

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => '___TEST___shared_tag',
            'value_type' => 'string',
            'label'      => 'Shared Tag',
        ], $db)->store();
        $this->createdKeyNames[] = '___TEST___shared_tag';

        // The form validator should already catch this, so calling updateExistingProperty()
        // directly here is testing the backstop, the same as a crafted submission would hit.
        $form = new TestableCustomVariableForm($db, $rootUuid);
        self::callMethod($form, 'updateExistingProperty', [
            [
                'key_name'    => '___TEST___shared_tag',
                'value_type'  => 'number',
                'label'       => 'Shared Tag',
                'description' => null,
            ]
        ]);

        $storedType = $dba->fetchOne(
            $dba->select()->from('director_property', ['value_type'])
                ->where('key_name = ?', '___TEST___shared_tag')
        );

        $this->assertSame(
            'string',
            $storedType,
            'value_type must stay put when a legacy Data Field still owns the varname'
        );
    }

    private function createHost(string $objectName, string $address): IcingaHost
    {
        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => $objectName,
            'object_type' => 'object',
            'address'     => $address,
        ], $db);
        $host->store();
        $this->createdHostNames[] = $objectName;

        return $host;
    }

    private function attachPropertyToHost(UuidInterface $propertyUuid, IcingaHost $host, $dba): void
    {
        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
            'required'      => 'n',
        ]);
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

            foreach ($this->createdDatafieldNames as $varname) {
                $dba->delete('director_datafield', $dba->quoteInto('varname = ?', $varname));
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
