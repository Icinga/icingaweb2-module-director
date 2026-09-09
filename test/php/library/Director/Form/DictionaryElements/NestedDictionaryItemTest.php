<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\NestedDictionary;
use Icinga\Module\Director\Forms\DictionaryElements\NestedDictionaryItem;
use PHPUnit\Framework\TestCase;

class NestedDictionaryItemTest extends TestCase
{
    public function testPreparePreservesAChildFieldLegitimatelyNamedKey(): void
    {
        $nestedItems = [
            ['key_name' => 'key', 'label' => 'API Key', 'value_type' => 'string'],
        ];
        $values = [
            'disk_root' => ['key' => 'sk-live-abc123'],
        ];

        $result = NestedDictionary::prepare($nestedItems, $values);

        $this->assertSame('sk-live-abc123', $result[0]['var'][0]['var']);
    }

    public function testPreparePreservesIntegerZeroValue(): void
    {
        $nestedItems = [
            ['key_name' => 'warn_threshold', 'label' => 'Warning Threshold', 'value_type' => 'number'],
        ];
        $property = ['warn_threshold' => 0];

        $result = NestedDictionaryItem::prepare($nestedItems, $property, 'disk_root');

        $this->assertSame(0, $result['var'][0]['var']);
    }

    public function testPreparePreservesFalseValue(): void
    {
        $nestedItems = [
            ['key_name' => 'monitoring_enabled', 'label' => 'Monitoring Enabled', 'value_type' => 'bool'],
        ];
        $property = ['monitoring_enabled' => false];

        $result = NestedDictionaryItem::prepare($nestedItems, $property, 'disk_root');

        $this->assertSame(false, $result['var'][0]['var']);
    }

    public function testPrepareStillOmitsTrulyUnsetValue(): void
    {
        $nestedItems = [
            ['key_name' => 'warn_threshold', 'label' => 'Warning Threshold', 'value_type' => 'number'],
        ];
        $property = [];

        $result = NestedDictionaryItem::prepare($nestedItems, $property, 'disk_root');

        $this->assertSame('', $result['var'][0]['var']);
    }
}
