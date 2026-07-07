<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionMethod;

class CustomVariablesFormTest extends BaseTestCase
{
    public function testAttachingNewPropertyToNonTemplateThrows(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'apitest',
            'object_type' => 'object',
        ]);
        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'assertCanAttachNewVariable');
        $method->setAccessible(true);

        $this->expectException(ProgrammingError::class);
        $method->invoke($form);
    }

    public function testAttachingNewPropertyToTemplateIsAllowed(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'linux-server',
            'object_type' => 'template',
        ]);

        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'assertCanAttachNewVariable');
        $method->setAccessible(true);

        $method->invoke($form);
        $this->addToAssertionCount(1);
    }

    public function testIsValueUnsetTreatsZeroStringAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, '0'));
    }

    public function testIsValueUnsetTreatsIntegerZeroAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, 0));
    }

    public function testIsValueUnsetTreatsFloatZeroAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, 0.0));
    }

    public function testIsValueUnsetTreatsFalseAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, false));
    }

    public function testIsValueUnsetTreatsEmptyStringAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, ''));
    }

    public function testIsValueUnsetTreatsNullAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, null));
    }

    public function testIsValueUnsetTreatsEmptyArrayAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, []));
    }

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
