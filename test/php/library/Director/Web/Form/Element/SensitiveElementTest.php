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
        $element->setValue('s3cr3t-value');

        $this->assertSame('s3cr3t-value', $element->getValue());
    }
}
