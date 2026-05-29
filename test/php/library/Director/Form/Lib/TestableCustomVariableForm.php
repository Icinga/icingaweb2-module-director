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

    public function setTestValues(array $values): void
    {
        $this->testValues = $values;
    }

    public function getValues(): array
    {
        return $this->testValues;
    }

    protected function assemble(): void
    {
    }
}
