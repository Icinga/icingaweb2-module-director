<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\PropertyModifier\PropertyModifierSimpleGroupBy;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierSimpleGroupByTest extends BaseTestCase
{
    public function testAggregatesDistinctDecodedJsonObjects(): void
    {
        $modifier = new PropertyModifierSimpleGroupBy();
        $modifier->setSettings(['aggregation_columns' => 'obj']);
        $first = (object) [
            'id' => 'a',
            'obj' => json_decode('{"field_a":"a"}'),
        ];
        $second = (object) [
            'id' => 'a',
            'obj' => json_decode('{"field_b":"b"}'),
        ];

        $this->assertSame('a', $modifier->setRow($first)->transform('a'));
        $this->assertSame('a', $modifier->setRow($second)->transform('a'));
        $this->assertTrue($modifier->rejectsRow());
        $this->assertEquals(
            [json_decode('{"field_a":"a"}'), json_decode('{"field_b":"b"}')],
            $first->obj
        );
    }

    public function testDeduplicatesEquivalentStructuredValues(): void
    {
        $modifier = new PropertyModifierSimpleGroupBy();
        $modifier->setSettings(['aggregation_columns' => 'obj']);
        $first = (object) ['obj' => json_decode('{"field":"same"}')];
        $same = (object) ['obj' => json_decode('{"field":"same"}')];
        $other = (object) ['obj' => json_decode('{"field":"other"}')];

        $modifier->setRow($first)->transform('a');
        $modifier->setRow($same)->transform('a');
        $modifier->setRow($other)->transform('a');

        $this->assertEquals(
            [json_decode('{"field":"same"}'), json_decode('{"field":"other"}')],
            $first->obj
        );
    }

    public function testKeepsScalarDeduplicationAndSorting(): void
    {
        $modifier = new PropertyModifierSimpleGroupBy();
        $modifier->setSettings(['aggregation_columns' => 'obj']);
        $first = (object) ['obj' => 'z'];
        foreach ([$first, (object) ['obj' => 'a'], (object) ['obj' => 'z']] as $row) {
            $modifier->setRow($row)->transform('a');
        }

        $this->assertSame(['a', 'z'], array_values($first->obj));
    }

    public function testSupportsArraysAlongsideObjectsWithoutCasting(): void
    {
        $modifier = new PropertyModifierSimpleGroupBy();
        $modifier->setSettings(['aggregation_columns' => 'obj']);
        $first = (object) ['obj' => ['nested' => 'value']];
        $other = (object) ['obj' => (object) ['nested' => 'value']];

        $modifier->setRow($first)->transform('a');
        $modifier->setRow($other)->transform('a');

        $this->assertSame(
            [['nested' => 'value'], (object) ['nested' => 'value']],
            $first->obj
        );
    }
}
