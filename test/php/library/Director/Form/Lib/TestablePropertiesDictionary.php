<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\Lib;

use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;

/**
 * Stands in for the 'properties' element persistPropertyChanges() reads values from,
 * skips building any real DictionaryItem widgets.
 */
class TestablePropertiesDictionary extends Dictionary
{
    private array $submittedValues;

    public function __construct(array $submittedValues)
    {
        parent::__construct('properties', []);

        $this->submittedValues = $submittedValues;
    }

    public function getDictionary(bool $applyUnchangedDefaults = true): array
    {
        return $this->submittedValues;
    }

    public function getRequiredFlags(): array
    {
        return [];
    }

    public function getItemsToRemove(): array
    {
        return [];
    }
}
