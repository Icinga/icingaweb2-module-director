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
            wholeValueCleared: false,
            blocked: false,
            children: $children,
            fixedArrayReindexes: []
        );
    }

    public function testUntouchedKeyWinsWhenListedBeforeTheRename(): void
    {
        $change = ['road' => new PropertyValueChange('road', 'street', false, false, [])];

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
        $change = ['road' => new PropertyValueChange('road', 'street', false, false, [])];

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
            'street' => new PropertyValueChange('street', 'road', false, false, []),
            'road' => new PropertyValueChange('road', 'street', false, false, []),
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
            'a' => new PropertyValueChange('a', 'b', false, false, []),
            'b' => new PropertyValueChange('b', 'c', false, false, []),
            'c' => new PropertyValueChange('c', 'a', false, false, []),
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
            'a' => new PropertyValueChange('a', 'b', false, false, []),
            'b' => new PropertyValueChange('b', 'c', false, false, []),
        ];

        $rebuilder = new PropertyValueRebuilder();
        $result = $rebuilder->rebuildRootValue(['a' => 'A', 'b' => 'B'], $this->migration($changes));

        $this->assertEquals(['b' => 'A', 'c' => 'B'], $result);
        $this->assertSame(0, $rebuilder->getConflictCount());
    }

    public function testDynamicDictionaryCollisionStaysLocalToEntry(): void
    {
        $change = ['road' => new PropertyValueChange('road', 'street', false, false, [])];

        $migration = new PropertyValueMigration(
            oldVarname: 'contacts',
            newVarname: 'contacts',
            oldRootType: 'dynamic-dictionary',
            wholeValueCleared: false,
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
}
