<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Web\Form\Element\SensitiveElement;
use PHPUnit\Framework\TestCase;
use Tests\Icinga\Module\Director\Form\Lib\TestableDictionaryItem;

/**
 * Currently only sensitive types are tested, the tests need to be extended
 * to cover all types.
 */
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

    public function testPrepareMasksAStoredSensitiveValueRatherThanExposingItInTheForm(): void
    {
        // The field can't tell a stored secret apart from a value the user just typed.
        // So prepare() must never send the real secret at all, or it would end up in
        // the page source on the very first load.
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame(SensitiveElement::DUMMYPASSWORD, $result['var']);
    }

    public function testPrepareLeavesVarEmptyWhenThereIsNoStoredSensitiveValue(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame('', $result['var']);
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
        // The parent template already has a value here (e.g. an SNMP community string
        // like "public"), so 'inherited' is set. The user leaves the field blank, so we
        // just keep the inherited value. This slot must store '' here, not null.
        $item = new TestableDictionaryItem('3', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-array',
            'inherited' => '1',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesExistingSensitiveValueWhenLeftUnchanged(): void
    {
        // Left untouched means the browser sends back the DUMMYPASSWORD placeholder,
        // not an empty string. Only that should keep the old secret, otherwise there
        // would be no way to ever clear it.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => SensitiveElement::DUMMYPASSWORD,
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-existing-value', $item->getItem()['value']);
    }

    public function testGetItemClearsExistingSensitiveValueWhenExplicitlyEmptied(): void
    {
        // An empty submission means the user cleared the field, so it must clear the
        // stored secret.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => '',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesExistingSensitiveValueWhenInheritedAndLeftUnchanged(): void
    {
        // 'inherited' is set whenever any parent also defines this property, even if
        // this object has its own value too. So getItem()'s "inherited" branch needs
        // the same unchanged-vs-cleared check as above.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'inherited' => '1',
            'var' => SensitiveElement::DUMMYPASSWORD,
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-existing-value', $item->getItem()['value']);
    }

    public function testGetItemClearsExistingSensitiveValueWhenInheritedAndExplicitlyEmptied(): void
    {
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'inherited' => '1',
            'var' => '',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }
}
