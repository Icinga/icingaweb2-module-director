<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\PropertyValueChange;
use Icinga\Module\Director\CustomVariable\PropertyValueMigration;
use Icinga\Module\Director\CustomVariable\PropertyValueRebuilder;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyValueRebuilderTest extends BaseTestCase
{
    private function migration(array $children): PropertyValueMigration
    {
        return new PropertyValueMigration(
            oldVarname: 'address',
            newVarname: 'address',
            oldRootType: 'fixed-dictionary',
            newRootType: 'fixed-dictionary',
            retyped: false,
            blocked: false,
            children: $children,
            fixedArrayReindexes: []
        );
    }

    public function testUntouchedKeyWinsWhenListedBeforeTheRename(): void
    {
        $change = ['road' => new PropertyValueChange('road', 'street', '', false, false, [])];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['street' => 'Main St', 'road' => 'Elm St'],
            $this->migration($change)
        );

        $this->assertEquals(['street' => 'Main St'], $result);
        $this->assertSame(1, $rebuilder->getConflictCount());
    }

    public function testUntouchedKeyWinsEvenWhenListedAfterTheRename(): void
    {
        $change = ['road' => new PropertyValueChange('road', 'street', '', false, false, [])];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['road' => 'Elm St', 'street' => 'Main St'],
            $this->migration($change)
        );

        // street is untouched, it must survive no matter where it sits in the
        // stored JSON, the incoming rename is the one that gets dropped.
        $this->assertEquals(['street' => 'Main St'], $result);
        $this->assertSame(1, $rebuilder->getConflictCount());
    }

    public function testSwapResolvesWithoutConflict(): void
    {
        $changes = [
            'street' => new PropertyValueChange('street', 'road', '', false, false, []),
            'road' => new PropertyValueChange('road', 'street', '', false, false, []),
        ];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['street' => 'Main St', 'road' => 'Elm St'],
            $this->migration($changes)
        );

        $this->assertEquals(['road' => 'Main St', 'street' => 'Elm St'], $result);
        $this->assertSame(0, $rebuilder->getConflictCount());
    }

    public function testRotationResolvesWithoutConflict(): void
    {
        $changes = [
            'a' => new PropertyValueChange('a', 'b', '', false, false, []),
            'b' => new PropertyValueChange('b', 'c', '', false, false, []),
            'c' => new PropertyValueChange('c', 'a', '', false, false, []),
        ];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['a' => 'A', 'b' => 'B', 'c' => 'C'],
            $this->migration($changes)
        );

        $this->assertEquals(['b' => 'A', 'c' => 'B', 'a' => 'C'], $result);
        $this->assertSame(0, $rebuilder->getConflictCount());
    }

    public function testChainResolvesWithoutConflict(): void
    {
        $changes = [
            'a' => new PropertyValueChange('a', 'b', '', false, false, []),
            'b' => new PropertyValueChange('b', 'c', '', false, false, []),
        ];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(['a' => 'A', 'b' => 'B'], $this->migration($changes));

        $this->assertEquals(['b' => 'A', 'c' => 'B'], $result);
        $this->assertSame(0, $rebuilder->getConflictCount());
    }

    public function testDynamicDictionaryCollisionStaysLocalToEntry(): void
    {
        $change = ['road' => new PropertyValueChange('road', 'street', '', false, false, [])];

        $migration = new PropertyValueMigration(
            oldVarname: 'contacts',
            newVarname: 'contacts',
            oldRootType: 'dynamic-dictionary',
            newRootType: 'dynamic-dictionary',
            retyped: false,
            blocked: false,
            children: $change,
            fixedArrayReindexes: []
        );

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            [
                'alice' => ['street' => 'Main St', 'road' => 'Elm St'],
                'bob' => ['road' => 'Oak Ave'],
            ],
            $migration
        );

        $this->assertEquals(
            ['alice' => ['street' => 'Main St'], 'bob' => ['street' => 'Oak Ave']],
            $result
        );
        $this->assertSame(1, $rebuilder->getConflictCount());
    }

    public function testRootRetypeKeepsAValueThatAlreadyFitsTheNewType(): void
    {
        // a host restored in the same basket as the retype writes its value
        // straight under the new type, that must not get wiped just because
        // the schema's type string changed
        $migration = new PropertyValueMigration(
            oldVarname: 'priority',
            newVarname: 'priority',
            oldRootType: 'string',
            newRootType: 'number',
            retyped: true,
            blocked: false,
            children: [],
            fixedArrayReindexes: []
        );

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(5, $migration);

        $this->assertSame(5, $result);
    }

    public function testRootRetypeStillClearsAValueThatDoesNotFitTheNewType(): void
    {
        $migration = new PropertyValueMigration(
            oldVarname: 'priority',
            newVarname: 'priority',
            oldRootType: 'string',
            newRootType: 'number',
            retyped: true,
            blocked: false,
            children: [],
            fixedArrayReindexes: []
        );

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue('urgent', $migration);

        $this->assertNull($result);
    }

    public function testRetypedChildKeepsAValueThatAlreadyFitsItsNewType(): void
    {
        // same idea, one level down, a nested field retyped in this restore
        // whose stored value already sits in the new shape
        $change = ['floor_count' => new PropertyValueChange('floor_count', 'floor_count', 'number', true, false, [])];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['floor_count' => 3, 'street' => 'Main St'],
            $this->migration($change)
        );

        $this->assertEquals(['street' => 'Main St', 'floor_count' => 3], $result);
    }

    public function testRetypedChildStillClearsAValueThatDoesNotFitItsNewType(): void
    {
        $change = ['floor_count' => new PropertyValueChange('floor_count', 'floor_count', 'number', true, false, [])];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(
            ['floor_count' => 'ground floor', 'street' => 'Main St'],
            $this->migration($change)
        );

        $this->assertEquals(['street' => 'Main St'], $result);
    }

    private function retypeMigration(string $newRootType): PropertyValueMigration
    {
        return new PropertyValueMigration(
            oldVarname: 'address',
            newVarname: 'address',
            oldRootType: 'fixed-dictionary',
            newRootType: $newRootType,
            retyped: true,
            blocked: false,
            children: [],
            fixedArrayReindexes: []
        );
    }

    public function testFlatValueFitsFixedDictionary(): void
    {
        $value = ['street' => 'Main St', 'zip' => '12345'];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue($value, $this->retypeMigration('fixed-dictionary'));

        $this->assertEquals($value, $result);
    }

    public function testFlatValueDoesNotFitDynamicDictionary(): void
    {
        $value = ['street' => 'Main St', 'zip' => '12345'];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue($value, $this->retypeMigration('dynamic-dictionary'));

        $this->assertNull($result);
    }

    public function testEntryValuesFitDynamicDictionary(): void
    {
        $value = ['alice' => ['role' => 'admin'], 'bob' => ['role' => 'user']];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue($value, $this->retypeMigration('dynamic-dictionary'));

        $this->assertEquals($value, $result);
    }

    public function testEntryValuesDoNotFitFixedDictionary(): void
    {
        $value = ['alice' => ['role' => 'admin'], 'bob' => ['role' => 'user']];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue($value, $this->retypeMigration('fixed-dictionary'));

        $this->assertNull($result);
    }

    public function testEmptyValueFitsFixedDictionary(): void
    {
        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue([], $this->retypeMigration('fixed-dictionary'));

        $this->assertEquals([], $result);
    }

    public function testEmptyValueDoesNotFitDynamicDictionary(): void
    {
        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue([], $this->retypeMigration('dynamic-dictionary'));

        $this->assertNull($result);
    }
}
