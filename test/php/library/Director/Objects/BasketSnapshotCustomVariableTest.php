<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshotCustomVariableResolver;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\RestApi\CustomVarApplyRequest;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\Test\BaseTestCase;
use LogicException;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for BasketSnapshot round-trip with DirectorProperty (custom variables).
 *
 * Scenario: a host template "linux-server" carries a disk_checks dynamic-dictionary property.
 * Snapshot, wipe, restore, and verify the system returns to its original state.
 */
class BasketSnapshotCustomVariableTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private const TEMPLATE_NAME = self::PREFIX . 'linux-server';
    private const PROP_KEY_NAME = self::PREFIX . 'disk_checks_bk';
    private const DIFF_LIST_NAME = self::PREFIX . 'diff_only_disk_list';
    private const MOUNT_POINTS_PROD_LIST = self::PREFIX . 'mount_points_prod';
    private const MOUNT_POINTS_STAGING_LIST = self::PREFIX . 'mount_points_staging';

    private const ORPHAN_TEMPLATE_NAME = self::PREFIX . 'basket-orphan-template';
    private const ORPHAN_TEMPLATE_ALT_NAME = self::PREFIX . 'basket-orphan-template-alt';
    private const ORPHAN_LEAF_NAME = self::PREFIX . 'basket-orphan-leaf';
    private const ORPHAN_PROP_KEY = self::PREFIX . 'basket_orphan_region';

    public function testSnapshotIncludesCustomVariableSection(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $json = $this->buildSnapshotJson($host, $property, $db);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey(
            'CustomVariable',
            $decoded,
            'Basket JSON must contain a CustomVariable section'
        );
    }

    public function testRestoreCreatesDirectorProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propUuid = Uuid::fromBytes($property->get('uuid'));

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId($propUuid, $db);
        $this->assertNotNull($restored, 'director_property row must be created by restore');
        $this->assertEquals(self::PROP_KEY_NAME, $restored->get('key_name'));
        $this->assertEquals('dynamic-dictionary', $restored->get('value_type'));
    }

    public function testRestoreBindsPropertyToTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restoredHost = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $restoredProp = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['cnt' => 'COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($restoredHost->get('uuid')), $dba)
                )
                ->where(
                    'property_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($restoredProp->get('uuid')), $dba)
                )
        );

        $this->assertEquals(1, (int) $count, 'icinga_host_property binding must be restored');
    }

    public function testRestoreUpdatesRequiredFlagOnExistingAttachment(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $exporter = new Exporter($db);
        $exportedHost = $exporter->export($host);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();
        foreach ($exportedHost->customVariables as $customVariable) {
            if ($customVariable->property_uuid === $propertyUuidString) {
                // createTemplateWithProperty() attaches this property without setting
                // 'required', so it defaults to 'n' - the snapshot below says it must
                // now be required.
                $customVariable->required = 'y';
            }
        }

        $json = json_encode([
            'HostTemplate' => [self::TEMPLATE_NAME => $exportedHost],
            'CustomVariable' => [$propertyUuidString => $property->export()],
        ]);

        // Restoring over the still-existing attachment (no wipe here) must update
        // 'required' in place, not skip it just because the link row already exists.
        BasketSnapshot::restoreJson($json, $db);

        $dba = $db->getDbAdapter();
        $required = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['required'])
                ->where('host_uuid = ?', DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba))
                ->where(
                    'property_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->get('uuid')), $dba)
                )
        );

        $this->assertEquals(
            'y',
            $required,
            'An existing attachment must have its required flag updated on restore, not left stale'
        );
    }

    /**
     * A host that imports a template can save its own value for a property the template
     * provides. Restoring a snapshot of that template which no longer lists the property
     * must clean that value up too, not just the template's own attachment row.
     */
    public function testRestoreOrphansDescendantValueWhenSnapshotDropsAttachment(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$template, $property] = $this->createOrphanTemplateWithProperty($db);
        $leaf = $this->createOrphanLeaf([self::ORPHAN_TEMPLATE_NAME], $db);
        $this->applyOrphanLeafValue($leaf, $db);

        $json = $this->buildOrphanSnapshotDroppingAttachment($template, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $leaf = IcingaHost::load(self::ORPHAN_LEAF_NAME, $db);
        $this->assertNull(
            $leaf->vars()->get(self::ORPHAN_PROP_KEY),
            'a descendant value must not survive once the restored template drops the property'
        );
    }

    /**
     * Same restore as above, but the host also imports a second template that keeps the
     * property attached. That template is untouched by this basket, so the host can still
     * reach the property through it, and its saved value must survive.
     */
    public function testRestoreKeepsDescendantValueWhenAnotherTemplateStillProvidesIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$template, $property] = $this->createOrphanTemplateWithProperty($db);
        $altTemplate = $this->createOrphanTemplate(self::ORPHAN_TEMPLATE_ALT_NAME, $db);
        $this->attachOrphanProperty($property, $altTemplate, $db);

        $leaf = $this->createOrphanLeaf([self::ORPHAN_TEMPLATE_NAME, self::ORPHAN_TEMPLATE_ALT_NAME], $db);
        $this->applyOrphanLeafValue($leaf, $db);

        $json = $this->buildOrphanSnapshotDroppingAttachment($template, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $leaf = IcingaHost::load(self::ORPHAN_LEAF_NAME, $db);
        $this->assertEquals(
            'eu-west',
            $leaf->vars()->get(self::ORPHAN_PROP_KEY)->getValue(),
            'a value still reachable through another template must survive the restore'
        );
    }

    public function testRestoreChildItemsForDictionary(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        // Add child fields to the dictionary
        foreach (['mount_point', 'warn', 'crit'] as $field) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $field,
                'parent_uuid' => $property->get('uuid'),
                'value_type'  => 'string',
            ], $db)->store();
        }

        // Re-load to pick up the fresh items
        $property = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );

        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $childKeys = array_map(
            fn($c) => $c->get('key_name'),
            $restored->fetchItemsFromDb()
        );
        sort($childKeys);

        $this->assertEquals(
            ['crit', 'mount_point', 'warn'],
            $childKeys,
            'All child items must be restored for the dictionary property'
        );
    }

    public function testRestoreRemovesChildItemsNotInSnapshot(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        foreach (['mount_point', 'warn'] as $field) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $field,
                'parent_uuid' => $property->get('uuid'),
                'value_type'  => 'string',
            ], $db)->store();
        }

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);

        // A field added on the target after the snapshot was taken must be removed
        // again on restore, not left sitting alongside the snapshot's own children.
        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'crit',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => 'string',
        ], $db)->store();

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $childKeys = array_map(fn($c) => $c->get('key_name'), $restored->fetchItemsFromDb());
        sort($childKeys);

        $this->assertEquals(
            ['mount_point', 'warn'],
            $childKeys,
            'A child that only exists on the target, not in the snapshot, must be removed on restore'
        );
    }

    public function testRestoreRenumbersChildItemsAfterDeletingOne(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $children = [];
        foreach (['0', '1', '2'] as $keyName) {
            $child = DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $keyName,
                'parent_uuid' => $property->get('uuid'),
                'value_type'  => 'string',
            ], $db);
            $child->store();
            $children[$keyName] = $child;
        }

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();
        $bUuid = Uuid::fromBytes($children['1']->get('uuid'))->toString();
        $cUuid = Uuid::fromBytes($children['2']->get('uuid'))->toString();

        // Drop slot "0" (A) and shift B and C down a slot, the renumber that used
        // to collide with the still-present A@0.
        $decoded = json_decode($json);
        $kept = [];
        foreach ($decoded->CustomVariable->{$propertyUuidString}->items as $item) {
            if ($item->uuid === $bUuid) {
                $item->key_name = '0';
                $kept[] = $item;
            } elseif ($item->uuid === $cUuid) {
                $item->key_name = '1';
                $kept[] = $item;
            }
        }

        $decoded->CustomVariable->{$propertyUuidString}->items = $kept;

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $restoredItems = $restored->fetchItemsFromDb();
        $byKeyName = [];
        foreach ($restoredItems as $item) {
            $byKeyName[$item->get('key_name')] = Uuid::fromBytes($item->get('uuid'))->toString();
        }

        $this->assertCount(2, $restoredItems, 'The deleted slot must not survive the restore');
        $this->assertEquals($bUuid, $byKeyName['0'] ?? null, 'B must now hold slot "0"');
        $this->assertEquals($cUuid, $byKeyName['1'] ?? null, 'C must now hold slot "1"');
    }

    public function testRestoreSwapsChildItemKeyNames(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $x = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $x->store();

        $y = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '1',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $y->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();
        $xUuid = Uuid::fromBytes($x->get('uuid'))->toString();
        $yUuid = Uuid::fromBytes($y->get('uuid'))->toString();

        // No deletion here, X and Y just trade slots.
        $decoded = json_decode($json);
        foreach ($decoded->CustomVariable->{$propertyUuidString}->items as $item) {
            if ($item->uuid === $xUuid) {
                $item->key_name = '1';
            } elseif ($item->uuid === $yUuid) {
                $item->key_name = '0';
            }
        }

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $byUuid = [];
        foreach ($restored->fetchItemsFromDb() as $item) {
            $byUuid[Uuid::fromBytes($item->get('uuid'))->toString()] = $item->get('key_name');
        }

        $this->assertEquals('1', $byUuid[$xUuid] ?? null, 'X must have swapped into slot "1"');
        $this->assertEquals('0', $byUuid[$yUuid] ?? null, 'Y must have swapped into slot "0"');
    }

    public function testRestoreRetypeCanDropAStaleSensitiveChild(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'secret',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db)->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();

        // Retyping to dynamic-array while dropping the sensitive child used to fail,
        // the parent got retyped before the child was gone and beforeStore() still saw it.
        $decoded = json_decode($json);
        $decoded->CustomVariable->{$propertyUuidString}->value_type = 'dynamic-array';
        $decoded->CustomVariable->{$propertyUuidString}->items = [];

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $this->assertEquals(
            'dynamic-array',
            $restored->get('value_type'),
            'Retyping away a stale sensitive child must not be rejected'
        );
        $this->assertCount(0, $restored->fetchItemsFromDb(), 'The dropped sensitive child must be deleted');
    }

    public function testRestoreAllowsNestedRetypeDroppingStaleSensitiveGrandchild(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        // fixed-dictionary can never nest, so use 'string' as the type before the retype
        $item = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'disk',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $item->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'secret',
            'parent_uuid' => $item->get('uuid'),
            'value_type'  => 'sensitive',
        ], $db)->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();
        $itemUuidString = Uuid::fromBytes($item->get('uuid'))->toString();

        // The nested item retypes to dynamic-array and drops its own sensitive child
        // in the same restore. Reordering the root property alone isn't enough, a
        // nested item needs the same care for its own children.
        $decoded = json_decode($json);
        foreach ($decoded->CustomVariable->{$propertyUuidString}->items as $exportedItem) {
            if ($exportedItem->uuid === $itemUuidString) {
                $exportedItem->value_type = 'dynamic-array';
                $exportedItem->items = [];
            }
        }

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restoredItem = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($item->get('uuid')), $db);
        $this->assertEquals(
            'dynamic-array',
            $restoredItem->get('value_type'),
            'Retyping a nested item away from a stale sensitive grandchild must not be rejected'
        );
        $this->assertCount(
            0,
            $restoredItem->fetchItemsFromDb(),
            'The dropped sensitive grandchild must be deleted'
        );
    }

    public function testRestoreAllowsLeavingUnmaskedTypeWhileAddingSensitiveChild(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        // Retype to an unmasked list type first, as if it already sits that way on
        // the target before this restore runs.
        $property->set('value_type', 'dynamic-array');
        $property->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();

        // Restore retypes the parent back to dynamic-dictionary and adds a new sensitive
        // child at the same time. Used to get rejected, the child stored before the
        // parent's own retype and beforeStore() still saw it as dynamic-array.
        $decoded = json_decode($json);
        $decoded->CustomVariable->{$propertyUuidString}->value_type = 'dynamic-dictionary';
        $decoded->CustomVariable->{$propertyUuidString}->items = [
            (object) [
                'uuid'        => Uuid::uuid4()->toString(),
                'key_name'    => 'secret',
                'value_type'  => 'sensitive',
                'label'       => null,
                'description' => null,
                'category'    => null,
            ],
        ];

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $this->assertEquals('dynamic-dictionary', $restored->get('value_type'));

        $children = $restored->fetchItemsFromDb();
        $this->assertCount(1, $children, 'The new sensitive child must be created');
        $this->assertEquals('sensitive', $children[0]->get('value_type'));
    }

    public function testRestoreRepointsDatalistWithoutOtherColumnChange(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $prodMountPoints = DirectorDatalist::create([
            'list_name' => self::MOUNT_POINTS_PROD_LIST,
            'owner'     => 'test',
        ], $db);
        $prodMountPoints->store();

        $stagingMountPoints = DirectorDatalist::create([
            'list_name' => self::MOUNT_POINTS_STAGING_LIST,
            'owner'     => 'test',
        ], $db);
        $stagingMountPoints->store();

        $property->set('value_type', 'datalist-strict');
        $property->assignDatalist($prodMountPoints);
        $property->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();

        // Someone repointed the field from the prod mount point list to the staging one.
        // value_type, label, everything else on the property stays the same, only the
        // linked list differs. store() used to see nothing modified and skip the write,
        // leaving the prod list attached on the target.
        $decoded = json_decode($json);
        $decoded->CustomVariable->{$propertyUuidString}->datalist = self::MOUNT_POINTS_STAGING_LIST;

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $restoredList = $restored->getDatalist();
        $this->assertNotNull($restoredList, 'Property must still have a linked datalist');
        $this->assertEquals(self::MOUNT_POINTS_STAGING_LIST, $restoredList->get('list_name'));
    }

    public function testRestoreClearsDatalistWhenTargetPropertyStillHasOne(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $prodMountPoints = DirectorDatalist::create([
            'list_name' => self::MOUNT_POINTS_PROD_LIST,
            'owner'     => 'test',
        ], $db);
        $prodMountPoints->store();

        $property->set('value_type', 'datalist-strict');
        $property->assignDatalist($prodMountPoints);
        $property->store();

        $property = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propertyUuidString = Uuid::fromBytes($property->get('uuid'))->toString();

        // export() reports "false" for a Data List field with no linked list, the same
        // shape as a field someone unlinked before taking this snapshot. Restoring that
        // must actually clear the mount point list still sitting on the target.
        $decoded = json_decode($json);
        $decoded->CustomVariable->{$propertyUuidString}->datalist = false;

        BasketSnapshot::restoreJson(json_encode($decoded), $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $this->assertNull($restored->getDatalist(), 'Datalist link must be cleared by restore');
    }

    public function testRestoreStampsPropertyUuidOnVarTable(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $host->vars()->set(self::PROP_KEY_NAME, (object) ['mount_point' => '/', 'warn' => 80]);
        $host->store();

        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restoredHost = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $restoredProp = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );

        $dba = $db->getDbAdapter();
        $storedUuid = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_var', ['property_uuid'])
                ->where(
                    'host_id = ?',
                    $restoredHost->get('id')
                )
                ->where('varname = ?', self::PROP_KEY_NAME)
        );

        $this->assertEquals(
            DbUtil::binaryResult($restoredProp->get('uuid')),
            DbUtil::binaryResult($storedUuid),
            'icinga_host_var.property_uuid must be stamped with the restored property uuid'
        );
    }

    public function testRestoreCreatesCategoryWhenMissingOnTarget(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $categoryName = self::PREFIX . 'disk_category';

        [$host, $property] = $this->createTemplateWithProperty($db);
        $property->setCategory($categoryName);
        $property->store();

        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);
        // The category has to be gone on the target too, that's the fresh-DB
        // case setCategory() used to get wrong.
        $dba->delete('director_datafield_category', $dba->quoteInto('category_name = ?', $categoryName));

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db);
        $this->assertEquals(
            $categoryName,
            $restored->getCategoryName(),
            'category_name must survive a restore onto a DB where the category does not exist yet'
        );

        // restoreJson() recreated the property (and its category link), so it must be wiped
        // again before the category delete below, or that delete hits director_property_category's
        // ON DELETE RESTRICT.
        $this->wipeTemplateAndProperty($host, $property, $db);
        $dba->delete('director_datafield_category', $dba->quoteInto('category_name = ?', $categoryName));
    }

    public function testRestoreIsIdempotent(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);
        BasketSnapshot::restoreJson($json, $db);

        $dba = $db->getDbAdapter();
        $propCount = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('uuid = ?', DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->get('uuid')), $dba))
        );

        $this->assertEquals(1, (int) $propCount, 'Restoring twice must not create duplicate properties');
    }

    public function testRelinkBeforeStoreNewPropertiesThrows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'resolver-order-host',
            'object_type' => 'template',
        ]);
        $host->store($db);

        $propertyUuid = Uuid::uuid4()->toString();
        $resolver = new BasketSnapshotCustomVariableResolver(
            [
                'CustomVariable' => [
                    $propertyUuid => (object) [
                        'uuid'        => $propertyUuid,
                        'key_name'    => self::PREFIX . 'resolver_order_prop',
                        'value_type'  => 'string',
                        'label'       => null,
                        'parent_uuid' => null,
                        'category'    => null,
                        'description' => null,
                        'items'       => [],
                    ],
                ],
            ],
            $db
        );

        $exportedObject = (object) [
            'customVariables' => [
                (object) ['property_uuid' => $propertyUuid],
            ],
        ];

        try {
            $this->expectException(LogicException::class);
            $resolver->relinkObjectCustomProperties($host, $exportedObject);
        } finally {
            $host->delete();
        }
    }

    public function testViewingDiffDoesNotCreateDatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $propertyUuid = Uuid::uuid4()->toString();
        $resolver = new BasketSnapshotCustomVariableResolver(
            [
                'CustomVariable' => [
                    $propertyUuid => (object) [
                        'uuid'        => $propertyUuid,
                        'key_name'    => self::PREFIX . 'diff_only_disk_list_prop',
                        'value_type'  => 'datalist-strict',
                        'label'       => null,
                        'parent_uuid' => null,
                        'category'    => null,
                        'description' => null,
                        'datalist'    => self::DIFF_LIST_NAME,
                        'items'       => [],
                    ],
                ],
            ],
            $db,
            true
        );

        // BasketDiff builds a resolver just like this to render a comparison and never calls
        // storeNewProperties() on it. Just viewing that comparison shouldn't write anything.
        $resolver->tweakTargetUuids((object) ['customVariables' => []]);

        $count = $dba->fetchOne(
            $dba->select()->from('director_datalist', ['cnt' => 'COUNT(*)'])
                ->where('list_name = ?', self::DIFF_LIST_NAME)
        );

        $this->assertEquals(0, (int) $count, 'Viewing a basket comparison must not create a datalist');
    }

    public function testBasketsWithoutCustomPropertiesStillWork(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        // Basket with a host template that has no custom properties key at all
        $templateName = self::PREFIX . 'no-props-template';
        $json = json_encode([
            'HostTemplate' => [
                $templateName => (object) [
                    'object_name' => $templateName,
                    'object_type' => 'template',
                ]
            ]
        ]);

        $this->expectNotToPerformAssertions();
        BasketSnapshot::restoreJson($json, $db);

        // Cleanup
        if (IcingaHost::exists($templateName, $db)) {
            IcingaHost::load($templateName, $db)->delete();
        }
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
                $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
                $dba->delete(
                    'icinga_host_property',
                    $dba->quoteInto(
                        'host_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                    )
                );
                $host->delete();
            }

            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', self::PROP_KEY_NAME)
            );
            foreach ($rows as $row) {
                $dba->delete(
                    'director_property',
                    $dba->quoteInto(
                        'parent_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($row->uuid), $dba)
                    )
                );
            }

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));
            $dba->delete('director_datalist', $dba->quoteInto('list_name = ?', self::DIFF_LIST_NAME));
            $dba->delete('director_datalist', $dba->quoteInto('list_name = ?', self::MOUNT_POINTS_PROD_LIST));
            $dba->delete('director_datalist', $dba->quoteInto('list_name = ?', self::MOUNT_POINTS_STAGING_LIST));

            // leaf imports both templates, so it has to go first
            $orphanHostNames = [
                self::ORPHAN_LEAF_NAME,
                self::ORPHAN_TEMPLATE_NAME,
                self::ORPHAN_TEMPLATE_ALT_NAME,
            ];

            foreach ($orphanHostNames as $hostName) {
                if (IcingaHost::exists($hostName, $db)) {
                    $host = IcingaHost::load($hostName, $db);
                    $dba->delete(
                        'icinga_host_property',
                        $dba->quoteInto(
                            'host_uuid = ?',
                            DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                        )
                    );
                    $host->delete();
                }
            }

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::ORPHAN_PROP_KEY));
        }

        parent::tearDown();
    }

    /**
     * @return array{IcingaHost, DirectorProperty}
     */
    private function createTemplateWithProperty($db): array
    {
        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        } else {
            $host = IcingaHost::create([
                'object_name' => self::TEMPLATE_NAME,
                'object_type' => 'template',
            ]);
            $host->store($db);
        }

        $dba = $db->getDbAdapter();
        $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));

        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PROP_KEY_NAME,
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Disk Checks',
        ], $db);
        $property->store();

        $dba = $db->getDbAdapter();
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        return [$host, $property];
    }

    private function buildSnapshotJson(IcingaHost $host, DirectorProperty $property, $db): string
    {
        $exporter = new Exporter($db);
        $exportedHost = $exporter->export($host);

        $exportedProperty = $property->export();
        $propertyUuid = Uuid::fromBytes($property->get('uuid'))->toString();

        $snapshot = [
            'HostTemplate' => [
                self::TEMPLATE_NAME => $exportedHost,
            ],
            'CustomVariable' => [
                $propertyUuid => $exportedProperty,
            ],
        ];

        return json_encode($snapshot);
    }

    private function wipeTemplateAndProperty(IcingaHost $host, DirectorProperty $property, $db): void
    {
        $dba = $db->getDbAdapter();
        $quotedHostUuid = DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba);
        $quotedPropUuid = DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->get('uuid')), $dba);

        $dba->delete('icinga_host_property', $dba->quoteInto('host_uuid = ?', $quotedHostUuid));
        $dba->delete('director_property', $dba->quoteInto('parent_uuid = ?', $quotedPropUuid));
        $dba->delete('director_property', $dba->quoteInto('uuid = ?', $quotedPropUuid));

        IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
    }

    /**
     * @return array{IcingaHost, DirectorProperty}
     */
    private function createOrphanTemplateWithProperty($db): array
    {
        $dba = $db->getDbAdapter();
        $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::ORPHAN_PROP_KEY));

        $template = $this->createOrphanTemplate(self::ORPHAN_TEMPLATE_NAME, $db);

        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::ORPHAN_PROP_KEY,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db);
        $property->store();

        $this->attachOrphanProperty($property, $template, $db);

        return [$template, $property];
    }

    private function createOrphanTemplate(string $name, $db): IcingaHost
    {
        if (IcingaHost::exists($name, $db)) {
            IcingaHost::load($name, $db)->delete();
        }

        $template = IcingaHost::create([
            'object_name' => $name,
            'object_type' => 'template',
        ]);
        $template->store($db);

        return $template;
    }

    private function attachOrphanProperty(DirectorProperty $property, IcingaHost $template, $db): void
    {
        $dba = $db->getDbAdapter();
        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
            'required'      => 'n',
        ]);
    }

    /**
     * @param string[] $importNames
     */
    private function createOrphanLeaf(array $importNames, $db): IcingaHost
    {
        if (IcingaHost::exists(self::ORPHAN_LEAF_NAME, $db)) {
            IcingaHost::load(self::ORPHAN_LEAF_NAME, $db)->delete();
        }

        $leaf = IcingaHost::create([
            'object_name' => self::ORPHAN_LEAF_NAME,
            'object_type' => 'object',
        ]);
        $leaf->store($db);
        $leaf->setImports($importNames);
        $leaf->store($db);

        return IcingaHost::load(self::ORPHAN_LEAF_NAME, $db);
    }

    private function applyOrphanLeafValue(IcingaHost $leaf, $db): void
    {
        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $leaf,
            [self::ORPHAN_PROP_KEY => 'eu-west'],
            'index',
            'POST',
            false
        ));
    }

    /**
     * A snapshot of $template with its own property attachment removed from the exported
     * customVariables list, as if the property had already been detached before the
     * snapshot was taken.
     */
    private function buildOrphanSnapshotDroppingAttachment(
        IcingaHost $template,
        DirectorProperty $property,
        $db
    ): string {
        $exporter = new Exporter($db);
        $exportedTemplate = $exporter->export($template);
        $propertyUuid = Uuid::fromBytes($property->get('uuid'))->toString();

        $exportedTemplate->customVariables = array_values(array_filter(
            (array) ($exportedTemplate->customVariables ?? []),
            fn($customVariable) => $customVariable->property_uuid !== $propertyUuid
        ));

        return json_encode([
            'HostTemplate' => [self::ORPHAN_TEMPLATE_NAME => $exportedTemplate],
        ]);
    }
}
