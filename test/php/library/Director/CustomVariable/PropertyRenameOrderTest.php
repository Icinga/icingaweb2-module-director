<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\PropertyRenameOrder;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyRenameOrderTest extends BaseTestCase
{
    public function testSingleRenameNeedsNoPlaceholder(): void
    {
        $result = (new PropertyRenameOrder())->resolve([
            'a' => ['old' => 'warn', 'new' => 'crit'],
        ]);

        $this->assertEquals(['a'], $result['order']);
        $this->assertEmpty($result['cycles']);
    }

    public function testNoOpRenameIsIgnored(): void
    {
        $result = (new PropertyRenameOrder())->resolve([
            'a' => ['old' => 'warn', 'new' => 'warn'],
        ]);

        $this->assertEmpty($result['order']);
        $this->assertEmpty($result['cycles']);
    }

    public function testChainIsOrderedTailFirstWithNoPlaceholder(): void
    {
        // a wants b's name, b wants c's name, c's new name is free
        $result = (new PropertyRenameOrder())->resolve([
            'a' => ['old' => 'warn', 'new' => 'crit'],
            'b' => ['old' => 'crit', 'new' => 'unit'],
            'c' => ['old' => 'unit', 'new' => 'warning'],
        ]);

        $this->assertEquals(['c', 'b', 'a'], $result['order']);
        $this->assertEmpty($result['cycles']);
    }

    public function testTwoWaySwapIsFlaggedAsACycle(): void
    {
        $result = (new PropertyRenameOrder())->resolve([
            'x' => ['old' => '0', 'new' => '1'],
            'y' => ['old' => '1', 'new' => '0'],
        ]);

        $this->assertEqualsCanonicalizing(['x', 'y'], $result['order']);
        $this->assertEqualsCanonicalizing(['x', 'y'], $result['cycles']);
    }

    public function testThreeWayRotationIsFlaggedAsACycle(): void
    {
        // a takes b's name, b takes c's name, c takes a's name, nobody's ever free
        $result = (new PropertyRenameOrder())->resolve([
            'a' => ['old' => '0', 'new' => '1'],
            'b' => ['old' => '1', 'new' => '2'],
            'c' => ['old' => '2', 'new' => '0'],
        ]);

        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], $result['order']);
        $this->assertEqualsCanonicalizing(['a', 'b', 'c'], $result['cycles']);
    }

    public function testChainLeadingIntoACycleOnlyFlagsTheCycleItself(): void
    {
        // w wants y's current name, but y and z are swapping with each other
        $result = (new PropertyRenameOrder())->resolve([
            'w' => ['old' => 'a', 'new' => 'y'],
            'y' => ['old' => 'y', 'new' => 'z'],
            'z' => ['old' => 'z', 'new' => 'y'],
        ]);

        $this->assertEqualsCanonicalizing(['w', 'y', 'z'], $result['order']);
        $this->assertEqualsCanonicalizing(['y', 'z'], $result['cycles']);
        $this->assertNotContains('w', $result['cycles']);
    }

    public function testUnrelatedGroupsAreEachResolvedOnTheirOwn(): void
    {
        $result = (new PropertyRenameOrder())->resolve([
            'a' => ['old' => 'warn', 'new' => 'crit'],
            'x' => ['old' => '0', 'new' => '1'],
            'y' => ['old' => '1', 'new' => '0'],
        ]);

        $this->assertEqualsCanonicalizing(['a', 'x', 'y'], $result['order']);
        $this->assertEqualsCanonicalizing(['x', 'y'], $result['cycles']);
    }
}
