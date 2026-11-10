<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;
use Tests\Icinga\Module\Director\Lib\Objects\TestableHostsCommand;

class HostsCommandTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testRefreshLinksVariablesOfTheCheckCommandAndLooksUpTheCommandsOnce(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $env = $this->createFixtureProperty('env');
        $this->createFixtureCommand('ping4', 'object', [], [$env]);
        $this->createFixtureHost('monitored-host', 'template', [], 'ping4');
        $hosts = [];
        foreach (range(1, 5) as $number) {
            // Three hosts use the command themselves, two through their template
            $hosts[] = $host = $number <= 3
                ? $this->createFixtureHost('host-' . $number, 'object', [], 'ping4')
                : $this->createFixtureHost('host-' . $number, 'object', ['monitored-host']);
            $this->storeFixtureValue($host, 'env', 'production');
            $this->assertNull($this->fetchFixtureLink($host, 'env'), 'the variables must start without a link');
        }

        $profiler = $this->getDb()->getDbAdapter()->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            (new TestableHostsCommand($this->getDb()))->refreshCustomVarsAction();
            $commandQueries = array_filter(
                $profiler->getQueryProfiles() ?: [],
                fn($profile) => str_contains($profile->getQuery(), 'icinga_command_property')
            );
        } finally {
            // The database connection is shared with the other tests
            $profiler->setEnabled(false);
            $profiler->clear();
        }

        $this->assertCount(1, $commandQueries, 'one lookup of the attachments of every command serves all hosts');
        foreach ($hosts as $host) {
            $this->assertSame($env->get('uuid'), $this->fetchFixtureLink($host, 'env'));
            $this->assertSame(
                'production',
                IcingaHost::loadWithAutoIncId((int) $host->get('id'), $this->getDb())->get('vars.' . $this->fx('env'))
            );
        }
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }
}
