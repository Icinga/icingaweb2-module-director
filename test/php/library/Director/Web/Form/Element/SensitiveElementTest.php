<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Form\Element;

use Icinga\Module\Director\Web\Form\Element\SensitiveElement;
use PHPUnit\Framework\TestCase;

class SensitiveElementTest extends TestCase
{
    public function testGetValueReturnsEmptyStringWhenNothingWasEverEntered(): void
    {
        $element = new SensitiveElement('api_token');

        $this->assertSame('', $element->getValue());
    }

    public function testGetValueReturnsTheEnteredValue(): void
    {
        $element = new SensitiveElement('api_token');
        $element->setValue('sk_live_4f8a1c9d2b7e');

        $this->assertSame('sk_live_4f8a1c9d2b7e', $element->getValue());
    }

    public function testWasSubmittedUnchangedIsFalseWhenNothingWasEverEntered(): void
    {
        $element = new SensitiveElement('api_token');

        $this->assertFalse($element->wasSubmittedUnchanged());
    }

    public function testWasSubmittedUnchangedIsFalseWhenExplicitlyEmptied(): void
    {
        $element = new SensitiveElement('api_token');
        $element->setValue('');

        $this->assertFalse($element->wasSubmittedUnchanged());
    }

    public function testWasSubmittedUnchangedIsFalseWhenGivenANewValue(): void
    {
        $element = new SensitiveElement('api_token');
        $element->setValue('sk_live_9d3e7b2a6f10');

        $this->assertFalse($element->wasSubmittedUnchanged());
    }

    public function testWasSubmittedUnchangedIsTrueWhenTheDummyPasswordSentinelComesBack(): void
    {
        $element = new SensitiveElement('api_token');
        $element->setValue(SensitiveElement::DUMMYPASSWORD);

        $this->assertTrue($element->wasSubmittedUnchanged());
    }

    public function testRenderedValueAttributeMasksTheDummyPasswordSentinel(): void
    {
        // DictionaryItem::prepare() always sends DUMMYPASSWORD instead of the real
        // secret, so this is what a fresh page load looks like, and also what a field
        // left untouched on resubmit looks like. Both must show up masked.
        $element = new SensitiveElement('api_token');
        $element->setValue(SensitiveElement::DUMMYPASSWORD);

        $html = (string) $element;

        $this->assertStringContainsString(SensitiveElement::DUMMYPASSWORD, $html);
    }

    public function testRenderedValueAttributeShowsAFreshlyTypedValue(): void
    {
        // Any value other than the sentinel is something the user just typed, so we show
        // it as-is. If we masked it too, saving again would look like "left unchanged"
        // and quietly bring back the old secret.
        $element = new SensitiveElement('api_token');
        $element->setValue('sk_live_00ff11ee22dd');

        $html = (string) $element;

        $this->assertStringContainsString('value="sk_live_00ff11ee22dd"', $html);
    }

    public function testRenderedValueAttributeIsAbsentWhenExplicitlyEmptied(): void
    {
        $element = new SensitiveElement('api_token');
        $element->setValue('');

        $html = (string) $element;

        $this->assertStringNotContainsString(SensitiveElement::DUMMYPASSWORD, $html);
    }
}
