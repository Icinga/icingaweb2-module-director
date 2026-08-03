<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\OverrideServiceVarsKeyValidator;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;

class OverrideServiceVarsKeyValidatorTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    /** @var string[] object_names of Services created during a test */
    private array $createdServices = [];

    private ?IcingaHost $host = null;

    public function testReturnsEmptyWhenNoOverrideVarsAreSet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = $this->createStoredHost('web1.example.com');

        $this->assertSame([], OverrideServiceVarsKeyValidator::findUnmatchedKeys($host));
    }

    public function testMatchesDirectlyAssignedService(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createStoredHost('web2.example.com');

        $serviceName = self::PREFIX . 'ssh';
        $this->createdServices[] = $serviceName;
        IcingaService::create([
            'object_name' => $serviceName,
            'object_type' => 'object',
            'host_id'     => $host->get('id'),
        ], $db)->store();

        $host->overrideServiceVars($serviceName, (object) ['ssh_port' => '2222']);
        $host->store();

        $this->assertSame([], OverrideServiceVarsKeyValidator::findUnmatchedKeys($host));
    }

    public function testFlagsAnUnrelatedKey(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = $this->createStoredHost('web3.example.com');
        $host->overrideServiceVars(self::PREFIX . 'ssl-cert-check', (object) ['warn_days' => '14']);
        $host->store();

        $this->assertSame(
            [self::PREFIX . 'ssl-cert-check'],
            OverrideServiceVarsKeyValidator::findUnmatchedKeys($host)
        );
    }

    public function testSkipsHostTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'generic-linux-server',
            'object_type' => 'template',
        ], $db);
        $host->overrideServiceVars(self::PREFIX . 'ssl-cert-check', (object) ['warn_days' => '14']);
        $host->store();
        $this->host = $host;

        $this->assertSame([], OverrideServiceVarsKeyValidator::findUnmatchedKeys($host));
    }

    public function testMatchesApplyRuleWhoseFilterMatchesTheHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createStoredHost('web4.example.com');

        // Mirrors a real Director apply-for rule name macro, whose vars.overriddenVar
        // ends up being this exact literal string, dollar signs included.
        $ruleName = self::PREFIX . 'http - $value$';
        $this->createdServices[] = $ruleName;
        IcingaService::create([
            'object_name'   => $ruleName,
            'object_type'   => 'apply',
            'assign_filter' => $this->hostNameFilter($host->getObjectName()),
        ], $db)->store();

        $host->overrideServiceVars($ruleName, (object) ['warn_days' => '14']);
        $host->store();

        $this->assertSame([], OverrideServiceVarsKeyValidator::findUnmatchedKeys($host));
    }

    public function testDoesNotMatchApplyRuleForAnotherHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->createStoredHost('web5.example.com');

        $ruleName = self::PREFIX . 'http - $value$';
        $this->createdServices[] = $ruleName;
        IcingaService::create([
            'object_name'   => $ruleName,
            'object_type'   => 'apply',
            'assign_filter' => $this->hostNameFilter(self::PREFIX . 'web6.example.com'),
        ], $db)->store();

        $host->overrideServiceVars($ruleName, (object) ['warn_days' => '14']);
        $host->store();

        $this->assertSame(
            [$ruleName],
            OverrideServiceVarsKeyValidator::findUnmatchedKeys($host)
        );
    }

    /**
     * Director stores assign_filter expression values JSON-encoded, e.g. host.name=%22web1%22.
     * ObjectApplyMatches::fixFilterExpressionColumn() json_decode()s them back, so a filter
     * built with a plain, unencoded value never matches.
     */
    private function hostNameFilter(string $hostName): string
    {
        return 'host.name=' . rawurlencode(json_encode($hostName));
    }

    private function createStoredHost(string $suffix): IcingaHost
    {
        $db = $this->getDb();
        $host = IcingaHost::create([
            'object_name' => self::PREFIX . $suffix,
            'object_type' => 'object',
        ], $db);
        $host->store();
        $this->host = $host;

        return $host;
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

            if ($this->host !== null && IcingaHost::exists($this->host->getObjectName(), $db)) {
                IcingaHost::load($this->host->getObjectName(), $db)->delete();
            }
        }

        parent::tearDown();
    }
}
