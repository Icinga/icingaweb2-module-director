<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for IcingaService apply-for header rendering driven by DirectorProperty.
 *
 * Scenario: a host template for disk monitoring (dynamic-dictionary) and HTTP monitoring
 * (dynamic-array). Apply-for services generate one service instance per entry.
 */
class IcingaServiceApplyForTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    protected $testHostName = self::PREFIX . 'host_apply_for';

    /** @var string[] service object_names created during tests */
    private array $createdServices = [];

    /** @var string[] property key_names created during tests */
    private array $createdPropertyKeys = [];

    public function testApplyForDynamicArrayRendersForValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->hostTemplate();
        $host->store($db);

        $this->makeAndLinkProperty('http_vhosts', 'dynamic-array', $host, $db);
        $applyFor = 'host.vars.' . self::PREFIX . 'http_vhosts';

        $service = $this->applyService('http-check', $applyFor);
        $service->setConnection($db);

        $rendered = (string) $service;

        $this->assertStringContainsString(
            'for (value in ' . $applyFor . ')',
            $rendered
        );

        $this->assertStringNotContainsString('key => value', $rendered);
    }

    public function testApplyForDynamicDictionaryRendersForKeyValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->hostTemplate();
        $host->store($db);

        $this->makeAndLinkProperty('disk_checks', 'dynamic-dictionary', $host, $db);
        $applyFor = 'host.vars.' . self::PREFIX . 'disk_checks';

        $service = $this->applyService('disk-check', $applyFor);
        $service->setConnection($db);

        $rendered = (string) $service;

        $this->assertStringContainsString(
            'for (key => value in ' . $applyFor . ')',
            $rendered
        );
    }

    public function testApplyForWithNoPropertyFallsBackToValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = $this->applyService('ping-check', 'host.vars.unknown_var');
        $service->setConnection($db);

        $rendered = (string) $service;

        $this->assertStringContainsString(
            'for (value in host.vars.unknown_var)',
            $rendered,
            'Apply-for with no matching director_property must fall back to (value in ...)'
        );
    }

    public function testValueFieldMacroAllowedInVars(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        // Create disk_checks (dynamic-dictionary) with child mount_point
        $parent = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'disk_checks_macro',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Disk Checks',
        ], $db);
        $parent->store();
        $this->createdPropertyKeys[] = self::PREFIX . 'disk_checks_macro';

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'mount_point',
            'parent_uuid' => $parent->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $child->store();

        $service = $this->applyService('disk-macro-check', 'host.vars.' . self::PREFIX . 'disk_checks_macro');
        $service->setConnection($db);
        $service->{'vars.mount'} = '$value.mount_point$';

        $rendered = (string) $service;

        $this->assertStringContainsString(
            'vars.mount = value.mount_point',
            $rendered,
            'Whitelisted value.mount_point macro must render as unquoted expression'
        );
    }

    public function testUnknownValueFieldStrippedFromVars(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        // Create disk_checks (dynamic-dictionary) with NO children — so value.not_a_real_field won't be whitelisted
        $parent = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'disk_checks_strip',
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Disk Checks Strip',
        ], $db);
        $parent->store();
        $this->createdPropertyKeys[] = self::PREFIX . 'disk_checks_strip';

        $service = $this->applyService('disk-strip-check', 'host.vars.' . self::PREFIX . 'disk_checks_strip');
        $service->setConnection($db);
        $service->{'vars.secret'} = '$value.not_a_real_field$';

        $rendered = (string) $service;

        $this->assertStringContainsString(
            'vars.secret = "$value.not_a_real_field$"',
            $rendered,
            'Non-whitelisted macro must render as quoted string'
        );
    }

    public function testRenderedArrayApplyMatchesFixture(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->hostTemplate();
        $host->store($db);

        $this->makeAndLinkProperty('http_vhosts_fix', 'dynamic-array', $host, $db);

        $service = $this->applyService('http-check', 'host.vars.' . self::PREFIX . 'http_vhosts_fix');
        $service->setConnection($db);
        $service->display_name = 'HTTP ' . chr(43) . ' value';
        $service->check_command = 'http';
        $service->{'vars.http_vhost'} = '$value$';
        $service->assign_filter = 'host.vars.' . self::PREFIX . 'http_vhosts_fix';

        $rendered = (string) $service;
        $fixture = file_get_contents(__DIR__ . '/rendered/service_apply_for_array.out');

        $this->assertEquals($fixture, $rendered);
    }

    public function testRenderedDictApplyMatchesFixture(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->hostTemplate();
        $host->store($db);

        $parent = $this->makeAndLinkProperty('disk_checks_fix', 'dynamic-dictionary', $host, $db);

        foreach (['mount_point', 'warn', 'crit'] as $fieldName) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $fieldName,
                'parent_uuid' => $parent->get('uuid'),
                'value_type'  => 'string',
            ], $db)->store();
        }

        $service = $this->applyService('disk-check', 'host.vars.' . self::PREFIX . 'disk_checks_fix');
        $service->setConnection($db);
        $service->display_name = 'Disk ' . chr(43) . ' key';
        $service->check_command = 'disk';
        $service->{'vars.disk_mount'} = '$value.mount_point$';
        $service->{'vars.disk_warn'}  = '$value.warn$';
        $service->{'vars.disk_crit'}  = '$value.crit$';
        $service->assign_filter = 'host.vars.' . self::PREFIX . 'disk_checks_fix';

        $rendered = (string) $service;
        $fixture = file_get_contents(__DIR__ . '/rendered/service_apply_for_dict.out');

        $this->assertEquals($fixture, $rendered);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            foreach ($this->createdServices as $serviceName) {
                if (IcingaService::exists(['object_name' => $serviceName], $db)) {
                    IcingaService::load(['object_name' => $serviceName], $db)->delete();
                }
            }

            if (IcingaHost::exists($this->testHostName, $db)) {
                IcingaHost::load($this->testHostName, $db)->delete();
            }

            foreach ($this->createdPropertyKeys as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $quotedUuid = DbUtil::quoteBinaryCompat(DbUtil::binaryResult($row->uuid), $dba);
                    $dba->delete('director_property', $dba->quoteInto('parent_uuid = ?', $quotedUuid));
                    $dba->delete('icinga_host_property', $dba->quoteInto('property_uuid = ?', $quotedUuid));
                }
                $dba->delete('director_property', $dba->quoteInto('key_name = ?', $keyName));
            }
        }

        parent::tearDown();
    }

    private function hostTemplate(): IcingaHost
    {
        return IcingaHost::create([
            'object_name' => $this->testHostName,
            'object_type' => 'template',
        ]);
    }

    private function applyService(string $serviceName, string $applyFor): IcingaService
    {
        $name = self::PREFIX . $serviceName;
        $this->createdServices[] = $name;
        return IcingaService::create([
            'object_name' => $name,
            'object_type' => 'apply',
            'apply_for'   => $applyFor,
        ]);
    }

    private function makeAndLinkProperty(
        string $suffix,
        string $valueType,
        IcingaHost $host,
        $db
    ): DirectorProperty {
        $keyName = self::PREFIX . $suffix;
        $this->createdPropertyKeys[] = $keyName;

        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $keyName,
            'value_type' => $valueType,
            'label'      => ucfirst(str_replace('_', ' ', $suffix)),
        ], $db);
        $property->store();

        $dba = $db->getDbAdapter();
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba),
        ]);

        return $property;
    }
}
