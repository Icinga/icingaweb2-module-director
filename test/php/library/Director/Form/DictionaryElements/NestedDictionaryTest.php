<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\NestedDictionary;
use PHPUnit\Framework\TestCase;

/**
 * Nested dictionary entry names during form submission
 */
class NestedDictionaryTest extends TestCase
{
    /**
     * Preserve a submitted zero entry name
     *
     * @return void
     */
    public function testGetDictionaryPreservesZeroKey(): void
    {
        $dictionary = new NestedDictionary('networks', [], []);
        $dictionary->populate([['key' => '0']]);

        $this->assertSame([0 => []], $dictionary->getDictionary());
    }

    /**
     * Preserve a submitted nonzero entry name
     *
     * @return void
     */
    public function testGetDictionaryPreservesNormalKey(): void
    {
        $dictionary = new NestedDictionary('networks', [], []);
        $dictionary->populate([['key' => 'office']]);

        $this->assertSame(['office' => []], $dictionary->getDictionary());
    }

    /**
     * Retain unnamed rows for validation
     *
     * @return void
     */
    public function testGetDictionaryMarksMissingKeysAsUndefined(): void
    {
        $dictionary = new NestedDictionary('networks', [], []);
        $dictionary->populate([[], ['key' => '']]);

        $this->assertSame(
            [NestedDictionary::UNDEFINED_KEY . '0' => [], NestedDictionary::UNDEFINED_KEY . '1' => []],
            $dictionary->getDictionary()
        );
    }

    /**
     * Preserve a stored zero entry name through preparation and submission
     *
     * @return void
     */
    public function testGetDictionaryPreservesPreparedZeroKey(): void
    {
        $values = [0 => []];
        $dictionary = new NestedDictionary('networks', [], [], $values);
        $dictionary->populate(NestedDictionary::prepare([], $values));

        $this->assertSame($values, $dictionary->getDictionary());
    }
}
