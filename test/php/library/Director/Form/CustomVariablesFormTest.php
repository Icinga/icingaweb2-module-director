<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Test\BaseTestCase;

class CustomVariablesFormTest extends BaseTestCase
{
    public function testFiltersEmptyStrings(): void
    {
        $result = CustomVariablesForm::filterEmpty(['ssl_verify' => '', 'http_address' => 'monitor.example.com']);
        $this->assertSame(['http_address' => 'monitor.example.com'], $result);
    }

    public function testKeepsBooleans(): void
    {
        $result = CustomVariablesForm::filterEmpty(['ssl_verify' => false, 'check_freshness' => true]);
        $this->assertSame(['ssl_verify' => false, 'check_freshness' => true], $result);
    }

    public function testFiltersNullValues(): void
    {
        $result = CustomVariablesForm::filterEmpty(['display_name' => null, 'check_command' => 'ping']);
        $this->assertSame(['check_command' => 'ping'], $result);
    }

    public function testFiltersIntegerZero(): void
    {
        $result = CustomVariablesForm::filterEmpty(['retry_count' => 0, 'max_check_attempts' => 3]);
        $this->assertSame(['max_check_attempts' => 3], $result);
    }

    public function testFiltersNestedEmptyArrays(): void
    {
        $result = CustomVariablesForm::filterEmpty(['disk_checks' => ['root' => ''], 'environment' => 'production']);
        $this->assertSame(['environment' => 'production'], $result);
    }

    public function testKeepsNestedArraysWithContent(): void
    {
        $input = ['disk_checks' => ['root' => '/'], 'environment' => 'production'];
        $this->assertSame($input, CustomVariablesForm::filterEmpty($input));
    }

    public function testEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], CustomVariablesForm::filterEmpty([]));
    }
}
