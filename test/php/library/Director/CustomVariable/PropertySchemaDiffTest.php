<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
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

        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($root);

        $this->assertTrue(
            $migration->isNoop(),
            'a property that never existed before has nothing stored to reconcile'
        );
    }

    public function testUnchangedTreeHasNothingToDiff(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['street', 'zip']);

        $plain = $root->export();
        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertTrue($migration->isNoop(), 'nothing changed, there is nothing to migrate');
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
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $rename = $migration->children['street'] ?? null;
        $this->assertNotNull($rename, 'the renamed child must show up as a change');
        $this->assertEquals('road', $rename->newKey);
        $this->assertFalse($rename->valueCleared);

        $delete = $migration->children['old_field'] ?? null;
        $this->assertNotNull($delete, 'the dropped child must show up as a change');
        $this->assertNull($delete->newKey, 'a dropped child has no slot left to move its value to');

        $this->assertArrayNotHasKey(
            'zip',
            $migration->children,
            'a child that did not change must not show up as a change at all'
        );
    }

    /**
     * A swap only resolves correctly if both sides are captured against the same
     * untouched original value. Applying a rebuild from this migration must produce
     * an actual swap, not one side's value getting lost.
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
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $cityChange = $migration->children['city'] ?? null;
        $townChange = $migration->children['town'] ?? null;

        $this->assertNotNull($cityChange, 'the first side of the swap must be detected');
        $this->assertEquals('town', $cityChange->newKey);
        $this->assertNotNull($townChange, 'the second side of the swap must be detected too');
        $this->assertEquals('city', $townChange->newKey);
    }

    public function testARetypeIsDetected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['zip']);

        $plain = $root->export();
        $plain->items['zip']->value_type = 'number';

        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $retype = $migration->children['zip'] ?? null;

        $this->assertNotNull($retype, 'a value_type change must show up as a change');
        $this->assertEquals('zip', $retype->newKey, 'a retype in place keeps its own key');
        $this->assertTrue($retype->valueCleared, 'a retyped value can not survive under the new type');
    }

    public function testRenamePlusRetypeOnTheSameNodeClearsAtItsFinalKey(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['zip']);

        $plain = $root->export();
        $plain->items['zip']->key_name = 'postal_code';
        $plain->items['zip']->value_type = 'number';

        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $change = $migration->children['zip'] ?? null;

        $this->assertNotNull($change);
        $this->assertEquals('postal_code', $change->newKey, 'the rename still has to happen');
        $this->assertTrue($change->valueCleared, 'the old value can not survive under the new type either way');
    }

    public function testUnchangedTreeWithARetainedLegacyDatafieldIsStillANoop(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['street', 'zip']);

        // Nothing here changed, so a Data Field that happens to share the root's
        // varname must not turn this into a blocked migration, it was never going
        // to touch that data in the first place.
        DirectorDatafield::create([
            'varname'  => self::ROOT_KEY_NAME,
            'caption'  => 'Address',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $plain = $root->export();
        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertTrue(
            $migration->isNoop(),
            'an untouched property must stay a no-op even if an unrelated Data Field shares its name'
        );
        $this->assertFalse(
            $migration->blocked,
            'nothing was pending, so there was nothing for the Data Field to block'
        );
    }

    public function testChangesAreNotAllowedWhenARootLegacyDatafieldOwnsTheData(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['street']);

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
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertTrue($migration->blocked, 'a legacy Data Field on the root must block the whole plan');

        $stillNamedStreet = current(array_filter(
            $imported->fetchItemsFromDb(),
            fn ($item) => $item->get('key_name') === 'street'
        ));
        $this->assertNotFalse(
            $stillNamedStreet,
            'a blocked rename must be undone in memory, not stored under a name its data cannot follow'
        );
    }

    public function testRootRenameIsDetected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, []);

        $plain = $root->export();
        $plain->key_name = self::ROOT_KEY_NAME . '_renamed';

        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertEquals(self::ROOT_KEY_NAME, $migration->oldVarname);
        $this->assertEquals(self::ROOT_KEY_NAME . '_renamed', $migration->newVarname);
        $this->assertFalse($migration->wholeValueCleared);
        $this->assertFalse($migration->blocked);
    }

    public function testRootRetypeClearsTheWholeValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, ['street']);

        $plain = $root->export();
        $plain->value_type = 'dynamic-dictionary';
        // A rename on a child no longer matters, the whole value is going away anyway.
        $plain->items['street']->key_name = 'road';

        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertTrue($migration->wholeValueCleared);
        $this->assertEmpty($migration->children, 'a cleared root has nothing left to migrate underneath it');

        $renamedChild = current(array_filter(
            $imported->fetchItemsFromDb(),
            fn ($item) => $item->get('key_name') === 'road'
        ));
        $this->assertNotFalse(
            $renamedChild,
            'the schema rename itself still goes through, only the stored value is dropped'
        );
    }

    public function testRootRenameBlockedByLegacyDatafieldIsUndoneInMemory(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, []);
        $newName = self::ROOT_KEY_NAME . '_taken';

        DirectorDatafield::create([
            'varname'  => $newName,
            'caption'  => 'Taken',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $plain = $root->export();
        $plain->key_name = $newName;

        $imported = DirectorProperty::import($plain, $db, true);
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $this->assertTrue($migration->blocked);
        $this->assertEquals(
            self::ROOT_KEY_NAME,
            $imported->get('key_name'),
            'a root rename blocked by a legacy Data Field must be undone in memory'
        );
        $this->assertEquals(
            $newName,
            $migration->newVarname,
            'a blocked migration must still say what the rename would have been, '
            . 'not just repeat the old name, a caller needs that to explain the block'
        );

        $dba = $db->getDbAdapter();
        $dba->delete('director_datafield', $dba->quoteInto('varname = ?', $newName));
    }

    public function testGrandchildChangesAreDetectedToo(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$root, ] = $this->createAddressWithChildren($db, []);

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
        $migration = (new PropertySchemaDiff(new CustomVariableValueCleaner($db)))->diff($imported);

        $billing = $migration->children['billing'] ?? null;
        $this->assertNotNull($billing, 'the unrenamed grouping node must still carry its changed child');

        $iban = $billing->children['iban'] ?? null;
        $this->assertNotNull($iban, 'a rename several levels deep must still be found');
        $this->assertEquals('account_number', $iban->newKey);
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
                    ->where('key_name LIKE ?', self::ROOT_KEY_NAME . '%')
            );
            foreach ($rows as $row) {
                $this->deleteWithDescendants($dba, DbUtil::binaryResult($row->uuid));
            }
            $dba->delete('director_property', $dba->quoteInto('key_name LIKE ?', self::ROOT_KEY_NAME . '%'));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'never_stored'));
            $dba->delete('director_datafield', $dba->quoteInto('varname LIKE ?', self::PREFIX . '%'));
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
