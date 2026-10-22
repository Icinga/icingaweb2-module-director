<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Form\Element;

use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use PHPUnit\Framework\TestCase;

class ArrayElementTest extends TestCase
{
    public function testStoredValueWithACommaSurvivesALoadAndSaveRoundTrip(): void
    {
        $element = new ArrayElement('locations');
        $element->setValue(['New York, NY', 'Remote']);

        $this->assertSame(['New York, NY', 'Remote'], $element->getValue());
    }

    public function testStoredValueWithAQuoteSurvivesALoadAndSaveRoundTrip(): void
    {
        $element = new ArrayElement('notes');
        $element->setValue(['say "hi"', 'plain']);

        $this->assertSame(['say "hi"', 'plain'], $element->getValue());
    }

    public function testTypedTransportTextStillSplitsIntoSeparateValues(): void
    {
        // this is what actually comes in from the browser widget, a single
        // comma separated string, not a stored list, still needs parsing
        $element = new ArrayElement('locations');
        $element->setValue('New York,Remote');

        $this->assertSame(['New York', 'Remote'], $element->getValue());
    }

    public function testInheritedPlaceholderShowsWhileThereAreNoOwnValues(): void
    {
        $element = (new ArrayElement('locations'))->setPlaceHolder('Berlin (Inherited from office-template)');

        $this->assertStringStartsWith(
            'Berlin (Inherited from office-template). ',
            $this->renderedPlaceholder($element)
        );
    }

    public function testInheritedPlaceholderDisappearsOnceOwnValuesAreSet(): void
    {
        $element = (new ArrayElement('locations'))->setPlaceHolder('Berlin (Inherited from office-template)');
        $element->setValue(['Hamburg']);

        $this->assertStringNotContainsString('Inherited from', $this->renderedPlaceholder($element));
    }

    private function renderedPlaceholder(ArrayElement $element): string
    {
        // The placeholder is worked out while rendering, so read it from the html
        preg_match('/placeholder="([^"]*)"/', $element->render(), $matches);

        return html_entity_decode($matches[1] ?? '');
    }
}
