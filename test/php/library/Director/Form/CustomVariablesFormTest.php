<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Security\SecurityException;
use LogicException;
use ReflectionMethod;

class CustomVariablesFormTest extends BaseTestCase
{
    /** @var string[] object_names of Services created by a test */
    private array $createdServices = [];

    private ?IcingaHost $createdHost = null;


    public function testAttachingNewPropertyToNonTemplateThrows(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'app-server-01',
            'object_type' => 'object',
        ]);
        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'assertCanAttachNewVariable');

        $this->expectException(LogicException::class);
        $method->invoke($form);
    }

    public function testAttachingNewPropertyToTemplateWithoutAdminThrows(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'linux-server',
            'object_type' => 'template',
        ]);

        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'assertCanAttachNewVariable');

        $this->expectException(SecurityException::class);
        $method->invoke($form);
    }

    public function testAttachingNewPropertyToTemplateAsAdminIsAllowed(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'linux-server',
            'object_type' => 'template',
        ]);

        $form = (new CustomVariablesForm($host))->setIsAdmin(true);
        $method = new ReflectionMethod($form, 'assertCanAttachNewVariable');

        $method->invoke($form);
        $this->addToAssertionCount(1);
    }

    public function testIsValueUnsetTreatsZeroStringAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertFalse($method->invoke(null, '0'));
    }

    public function testIsValueUnsetTreatsIntegerZeroAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertFalse($method->invoke(null, 0));
    }

    public function testIsValueUnsetTreatsFloatZeroAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertFalse($method->invoke(null, 0.0));
    }

    public function testIsValueUnsetTreatsFalseAsSet(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertFalse($method->invoke(null, false));
    }

    public function testIsValueUnsetTreatsEmptyStringAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertTrue($method->invoke(null, ''));
    }

    public function testIsValueUnsetTreatsNullAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertTrue($method->invoke(null, null));
    }

    public function testIsValueUnsetTreatsEmptyArrayAsUnset(): void
    {
        $method = new ReflectionMethod(CustomVariablesForm::class, 'isValueUnset');

        $this->assertTrue($method->invoke(null, []));
    }

    public function testOverrideWithoutHostThrows(): void
    {
        $service = IcingaService::create([
            'object_name' => 'ping',
            'object_type' => 'template',
        ]);
        $form = new CustomVariablesForm($service);
        $form->setApplyGenerated($service);

        $method = new ReflectionMethod($form, 'assertOverrideHostIsSet');

        $this->expectException(LogicException::class);
        $method->invoke($form);
    }

    public function testOverrideWithHostIsAllowed(): void
    {
        $service = IcingaService::create([
            'object_name' => 'ping',
            'object_type' => 'template',
        ]);
        $host = IcingaHost::create([
            'object_name' => 'linux-server',
            'object_type' => 'template',
        ]);
        $form = new CustomVariablesForm($service);
        $form->setApplyGenerated($service);
        $form->setHostForService($host);

        $method = new ReflectionMethod($form, 'assertOverrideHostIsSet');

        $method->invoke($form);
        $this->addToAssertionCount(1);
    }

    public function testNoOverrideRequestedDoesNotThrow(): void
    {
        $host = IcingaHost::create([
            'object_name' => 'linux-server',
            'object_type' => 'template',
        ]);
        $form = new CustomVariablesForm($host);

        $method = new ReflectionMethod($form, 'assertOverrideHostIsSet');

        $method->invoke($form);
        $this->addToAssertionCount(1);
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

    public function testKeepsIntegerZero(): void
    {
        $result = CustomVariablesForm::filterEmpty(['retry_count' => 0, 'max_check_attempts' => 3]);
        $this->assertSame(['retry_count' => 0, 'max_check_attempts' => 3], $result);
    }

    public function testKeepsStringZero(): void
    {
        $result = CustomVariablesForm::filterEmpty(['exit_code' => '0', 'label' => 'ok']);
        $this->assertSame(['exit_code' => '0', 'label' => 'ok'], $result);
    }

    public function testKeepsFloatZero(): void
    {
        $result = CustomVariablesForm::filterEmpty(['load_offset' => 0.0, 'label' => 'ok']);
        $this->assertSame(['load_offset' => 0.0, 'label' => 'ok'], $result);
    }

    public function testFixedArrayWithLegitimateZeroIsNotDropped(): void
    {
        // The list branch must also treat a real 0 as "this list has content", not as empty.
        $result = CustomVariablesForm::filterEmpty([0, '', '']);
        $this->assertSame([0, '', ''], $result);
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

    public function testFixedArrayWithAllEmptySlotsIsDropped(): void
    {
        $result = CustomVariablesForm::filterEmpty(['', '', '']);
        $this->assertSame([], $result);
    }

    public function testFixedArrayNestedInsideDictionaryEntryKeepsItsSlots(): void
    {
        // e.g. a dynamic-dictionary entry ("dc1" => [...]) or a fixed-dictionary field that
        // itself holds a fixed-array -- the nested fixed-array must not lose its positions.
        $entry = ['label' => 'dc1', 'slots' => ['eth0', '', 'eth2']];
        $result = CustomVariablesForm::filterEmpty($entry);
        $this->assertSame(['label' => 'dc1', 'slots' => ['eth0', '', 'eth2']], $result);
    }

    public function testFixedArrayNestedInsideDictionaryEntryIsDroppedWhenFullyEmpty(): void
    {
        $entry = ['label' => 'dc1', 'slots' => ['', '', '']];
        $result = CustomVariablesForm::filterEmpty($entry);
        $this->assertSame(['label' => 'dc1'], $result);
    }

    public function testOverrideVarsWarningIsNullForAMatchingKey(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create(
            ['object_name' => '___TEST___web1.example.com', 'object_type' => 'object'],
            $db
        );
        $host->store();
        $this->createdHost = $host;

        $serviceName = '___TEST___ssh';
        $this->createdServices[] = $serviceName;
        IcingaService::create(
            ['object_name' => $serviceName, 'object_type' => 'object', 'host_id' => $host->get('id')],
            $db
        )->store();
        $host->overrideServiceVars($serviceName, (object) ['ssh_port' => '2222']);
        $host->store();

        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'buildOverrideVarsWarning');

        $this->assertNull($method->invoke($form));
    }

    public function testOverrideVarsWarningFlagsAnUnmatchedKey(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create(
            ['object_name' => '___TEST___web2.example.com', 'object_type' => 'object'],
            $db
        );
        $host->overrideServiceVars('___TEST___ssl-cert-check', (object) ['warn_days' => '14']);
        $host->store();
        $this->createdHost = $host;

        $form = new CustomVariablesForm($host);
        $method = new ReflectionMethod($form, 'buildOverrideVarsWarning');

        $warning = $method->invoke($form);
        $this->assertNotNull($warning);
        $this->assertStringContainsString('___TEST___ssl-cert-check', (string) $warning);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();

            foreach ($this->createdServices as $serviceName) {
                if (IcingaService::exists(['object_name' => $serviceName], $db)) {
                    IcingaService::load(['object_name' => $serviceName], $db)->delete();
                }
            }

            if ($this->createdHost !== null && IcingaHost::exists($this->createdHost->getObjectName(), $db)) {
                IcingaHost::load($this->createdHost->getObjectName(), $db)->delete();
            }
        }

        parent::tearDown();
    }
}
