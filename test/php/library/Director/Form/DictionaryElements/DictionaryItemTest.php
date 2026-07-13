<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use PHPUnit\Framework\TestCase;
use Tests\Icinga\Module\Director\Form\DictionaryElements\Lib\TestableDictionaryItem;

class DictionaryItemTest extends TestCase
{
    public function testPrepareScrubsInheritedSecretButKeepsItsPresenceForSensitiveType(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
            'inherited' => 's3cr3t-inherited-value',
            'inherited_from' => 'webserver-template',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertStringNotContainsString('s3cr3t-inherited-value', $result['inherited']);
        $this->assertNotEmpty($result['inherited'], 'presence of an inherited value must still be signaled');
    }

    public function testPrepareLeavesInheritedEmptyWhenNothingIsInheritedForSensitiveType(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame('', $result['inherited']);
    }

    public function testGetItemDefaultsSensitiveValueToEmptyStringInFixedArray(): void
    {
        $item = new TestableDictionaryItem('0', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-array',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesEnteredSensitiveValue(): void
    {
        $item = new TestableDictionaryItem('api_token', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => 's3cr3t-value',
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-value', $item->getItem()['value']);
    }

    public function testGetItemDefaultsSensitiveValueToEmptyStringWhenInheritedAndLeftBlank(): void
    {
        // A parent template's own ssh_args has a non-empty value at this sensitive slot
        // (e.g. "afafaf"), so 'inherited' is truthy here. The user leaves the field blank,
        // accepting/ignoring the inherited value rather than typing an override. The
        // overriding array must still store '' at this position, not a stray null.
        $item = new TestableDictionaryItem('3', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-array',
            'inherited' => '1',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }
}
