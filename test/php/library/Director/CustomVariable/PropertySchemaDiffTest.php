<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
use Icinga\Module\Director\CustomVariable\PropertyChange;
use Icinga\Module\Director\CustomVariable\PropertySchemaDiff;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class PropertySchemaDiffTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private const ROOT_KEY_NAME = self::PREFIX . 'diff_address';

    public function testBrandNewPropertyHasNothingToDiff(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $root = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'never_stored',
            'value_type' => 'fixed-dictionary',
        ], $db);

        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($root);

        $this->assertEmpty($changes, 'a property that never existed before has nothing stored to reconcile');
    }

    public function testDetectsARenameAndADelete(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, $children] = $this->createAddressWithChildren($db, ['street', 'zip', 'old_field']);

        $plain = $root->export();
        $plain->items['street']->key_name = 'road';
        unset($plain->items['old_field']);

        $imported = DirectorProperty::import($plain, $db, true);
        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $rename = $this->findChange($changes, PropertyChange::RENAME);
        $delete = $this->findChange($changes, PropertyChange::DELETE);

        $this->assertNotNull($rename, 'the renamed child must show up as a rename change');
        $this->assertEquals($children['street']->get('uuid'), $rename->property->get('uuid'));
        $this->assertTrue($rename->allowed);

        $this->assertNotNull($delete, 'the dropped child must show up as a delete change');
        $this->assertEquals($children['old_field']->get('uuid'), $delete->property->get('uuid'));

        $this->assertNull(
            $this->findChangeForUuid($changes, $children['zip']->get('uuid')),
            'a child that did not change must not show up as a change at all'
        );
    }

    /**
     * A swap is still just two independent renames as far as stored values go,
     * moving one never depends on the other having moved first. The safe write
     * order for the schema itself is a separate concern, handled elsewhere.
     */
    public function testBothSidesOfASwapAreDetected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, $children] = $this->createAddressWithChildren($db, ['city', 'town']);

        $plain = $root->export();
        $plain->items['city']->key_name = 'town';
        $plain->items['town']->key_name = 'city';

        $imported = DirectorProperty::import($plain, $db, true);
        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $cityChange = $this->findChangeForUuid($changes, $children['city']->get('uuid'));
        $townChange = $this->findChangeForUuid($changes, $children['town']->get('uuid'));

        $this->assertNotNull($cityChange, 'the first side of the swap must be detected');
        $this->assertNotNull($townChange, 'the second side of the swap must be detected too');
        $this->assertTrue($cityChange->allowed);
        $this->assertTrue($townChange->allowed);
    }

    public function testARetypeIsDetected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, $children] = $this->createAddressWithChildren($db, ['zip']);

        $plain = $root->export();
        $plain->items['zip']->value_type = 'number';

        $imported = DirectorProperty::import($plain, $db, true);
        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $retype = $this->findChange($changes, PropertyChange::RETYPE);

        $this->assertNotNull($retype, 'a value_type change must show up as a retype change');
        $this->assertEquals($children['zip']->get('uuid'), $retype->property->get('uuid'));
        $this->assertTrue($retype->allowed);
    }

    public function testChangesAreNotAllowedWhenARootLegacyDatafieldOwnsTheData(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, $children] = $this->createAddressWithChildren($db, ['street']);

        // A legacy Data Field under the root's own varname means we can't tell
        // whose data is stored there, so nothing nested under it may be touched.
        DirectorDatafield::create([
            'varname'  => self::ROOT_KEY_NAME,
            'caption'  => 'Address',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $plain = $root->export();
        $plain->items['street']->key_name = 'road';

        $imported = DirectorProperty::import($plain, $db, true);
        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $rename = $this->findChange($changes, PropertyChange::RENAME);

        $this->assertNotNull($rename);
        $this->assertFalse($rename->allowed, 'a legacy Data Field on the root must block the rename below it');
    }

    public function testGrandchildChangesAreDetectedToo(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, $children] = $this->createAddressWithChildren($db, []);

        // fixed-dictionary can only be used at the top level, so this middle
        // node has to be a plain string, it's just a grouping node for this test
        $childUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $childUuid->getBytes(),
            'key_name'    => 'billing',
            'parent_uuid' => $root->get('uuid'),
            'value_type'  => 'string',
        ], $db)->store();

        $grandchildUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'        => $grandchildUuid->getBytes(),
            'key_name'    => 'iban',
            'parent_uuid' => $childUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $root = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($root->get('uuid')), $db);
        $plain = $root->export();
        $plain->items['billing']->items['iban']->key_name = 'account_number';

        $imported = DirectorProperty::import($plain, $db, true);
        $changes = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $rename = $this->findChangeForUuid($changes, $grandchildUuid->getBytes());

        $this->assertNotNull($rename, 'a rename several levels deep must still be found');
        $this->assertEquals(PropertyChange::RENAME, $rename->kind);
        $this->assertTrue($rename->allowed);
    }

    /**
     * @param PropertyChange[] $changes
     */
    private function findChange(array $changes, string $kind): ?PropertyChange
    {
        foreach ($changes as $change) {
            if ($change->kind === $kind) {
                return $change;
            }
        }

        return null;
    }

    /**
     * @param PropertyChange[] $changes
     */
    private function findChangeForUuid(array $changes, string $uuid): ?PropertyChange
    {
        foreach ($changes as $change) {
            if ($change->property->get('uuid') === $uuid) {
                return $change;
            }
        }

        return null;
    }

    /**
     * @param string[] $childKeyNames
     *
     * @return array{0: DirectorProperty, 1: array<string, DirectorProperty>}
     */
    private function createAddressWithChildren($db, array $childKeyNames): array
    {
        $rootUuid = Uuid::uuid4();
        $root = DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::ROOT_KEY_NAME,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Address',
        ], $db);
        $root->store();

        $children = [];
        foreach ($childKeyNames as $keyName) {
            $child = DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $keyName,
                'parent_uuid' => $rootUuid->getBytes(),
                'value_type'  => 'string',
            ], $db);
            $child->store();
            $children[$keyName] = $child;
        }

        $root = DirectorProperty::loadWithUniqueId($rootUuid, $db);

        return [$root, $children];
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])
                    ->where('key_name = ?', self::ROOT_KEY_NAME)
            );
            foreach ($rows as $row) {
                $this->deleteWithDescendants($dba, DbUtil::binaryResult($row->uuid));
            }
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::ROOT_KEY_NAME));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'never_stored'));
            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', self::ROOT_KEY_NAME));
        }

        parent::tearDown();
    }

    private function deleteWithDescendants($dba, string $rawUuid): void
    {
        $quotedUuid = DbUtil::quoteBinaryCompat($rawUuid, $dba);
        $childRows = $dba->fetchAll(
            $dba->select()->from('director_property', ['uuid'])->where('parent_uuid = ?', $quotedUuid)
        );
        foreach ($childRows as $childRow) {
            $this->deleteWithDescendants($dba, DbUtil::binaryResult($childRow->uuid));
        }
        $dba->delete('director_property', $dba->quoteInto('parent_uuid = ?', $quotedUuid));
    }
}
