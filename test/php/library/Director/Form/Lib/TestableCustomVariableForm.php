<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\Lib;

use Icinga\Module\Director\Forms\CustomVariableForm;

/**
 * Test-only subclass that bypasses the CSRF/session requirement in assemble()
 * and allows injecting form values directly without submitting a request.
 */
class TestableCustomVariableForm extends CustomVariableForm
{
    private array $testValues = [];

    private ?int $forcedUsedCount = null;

    public function setTestValues(array $values): void
    {
        $this->testValues = $values;
    }

    /**
     * assemble() is stubbed out, so there is no real used_count element to read from.
     * Call this to fake what getValue('used_count') would return.
     */
    public function setForcedUsedCount(int $usedCount): void
    {
        $this->forcedUsedCount = $usedCount;
    }

    public function getValue($name, $default = null)
    {
        if ($name === 'used_count' && $this->forcedUsedCount !== null) {
            return $this->forcedUsedCount;
        }

        return $default;
    }

    public function getValues(): array
    {
        return $this->testValues;
    }

    protected function assemble(): void
    {
    }
}
