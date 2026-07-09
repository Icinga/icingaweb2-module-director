<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\NestedDictionaryItem;
use PHPUnit\Framework\TestCase;

class NestedDictionaryItemTest extends TestCase
{
    public function testPreparePreservesIntegerZeroValue(): void
    {
        $nestedItems = [
            ['key_name' => 'warn_threshold', 'label' => 'Warning Threshold', 'value_type' => 'number'],
        ];
        $property = ['key' => 'disk_root', 'warn_threshold' => 0];

        $result = NestedDictionaryItem::prepare($nestedItems, $property);

        $this->assertSame(0, $result['var'][0]['var']);
    }

    public function testPreparePreservesFalseValue(): void
    {
        $nestedItems = [
            ['key_name' => 'monitoring_enabled', 'label' => 'Monitoring Enabled', 'value_type' => 'bool'],
        ];
        $property = ['key' => 'disk_root', 'monitoring_enabled' => false];

        $result = NestedDictionaryItem::prepare($nestedItems, $property);

        $this->assertSame(false, $result['var'][0]['var']);
    }

    public function testPrepareStillOmitsTrulyUnsetValue(): void
    {
        $nestedItems = [
            ['key_name' => 'warn_threshold', 'label' => 'Warning Threshold', 'value_type' => 'number'],
        ];
        $property = ['key' => 'disk_root'];

        $result = NestedDictionaryItem::prepare($nestedItems, $property);

        $this->assertSame('', $result['var'][0]['var']);
    }
}
