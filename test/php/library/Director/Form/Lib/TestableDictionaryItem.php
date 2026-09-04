<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\Lib;

use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Web\Form\Element\SensitiveElement;

/**
 * Test adapter that bypasses the DB-backed assemble() of DictionaryItem, registering
 * only the specific elements a getItem() scenario needs.
 */
class TestableDictionaryItem extends DictionaryItem
{
    private array $testConfig = [];

    public function setTestConfig(array $config): void
    {
        $this->testConfig = $config;
    }

    protected function assemble(): void
    {
        $this->addElement('hidden', 'name', ['value' => $this->testConfig['name'] ?? '']);
        $this->addElement('hidden', 'type', ['value' => $this->testConfig['type'] ?? '']);
        $this->addElement('hidden', 'parent_type', ['value' => $this->testConfig['parent_type'] ?? '']);
        $this->addElement('hidden', 'inherited', ['value' => $this->testConfig['inherited'] ?? '']);
        $this->addElement('hidden', 'inherited_from', ['value' => $this->testConfig['inherited_from'] ?? '']);

        $varOptions = [];
        if (isset($this->testConfig['var'])) {
            $varOptions['value'] = $this->testConfig['var'];
        }

        if (($this->testConfig['type'] ?? '') === 'sensitive') {
            $this->addElement(new SensitiveElement('var', $varOptions));
        } else {
            $this->addElement('password', 'var', $varOptions);
        }
    }
}
