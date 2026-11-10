<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Import;

use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Test\SyncTest;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class SyncCheckCommandVariableTest extends SyncTest
{
    use CommandVariableFixture;

    /** @var string */
    protected $objectType = 'host';

    /** @var string */
    protected $keyColumn = 'host';

    public function testSyncLinksAndUnlinksAValueByTheCheckCommandOfTheHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $this->setUpHostProperties();

        $this->runImport([$this->row('check-disk', 'synced', 'first')]);
        $this->sync->apply();
        $host = IcingaHost::load('SYNCTEST_web01', $this->getDb());
        $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($host, 'warning'));

        $this->runNextImport([$this->row('check-ping', 'synced', 'first')]);
        $this->resync();
        $this->sync->apply();
        $this->assertNull($this->fetchFixtureLink($host, 'warning'), 'sync unlinks the value and keeps it');

        $this->runNextImport([$this->row('check-disk', 'synced', 'first')]);
        $this->resync();
        $this->sync->apply();
        $this->runNextImport([$this->row('check-ping', 'synced', 'second')]);
        $this->resync();
        $this->sync->apply();
        $this->assertNull(
            $this->fetchFixtureLink($host, 'warning'),
            'another field changing in the same run does not keep the old link'
        );
    }

    public function testSyncLooksUpTheCommandsOnceForManyHosts(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->setUpHostProperties();
        $rows = [];
        foreach (range(1, 5) as $number) {
            $rows[] = ['host' => 'SYNCTEST_web0' . $number] + $this->row('check-disk', 'synced', 'first');
        }

        $this->runImport($rows);

        $profiler = $this->getDb()->getDbAdapter()->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $this->sync->apply();
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
        foreach (range(1, 5) as $number) {
            $host = IcingaHost::load('SYNCTEST_web0' . $number, $this->getDb());
            $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($host, 'warning'));
        }
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            // the synced objects use the fixture commands, and hosts go before their templates
            foreach (['SYNCTEST_web01', 'SYNCTEST_linux'] as $name) {
                if (IcingaHost::exists($name, $this->getDb())) {
                    IcingaHost::load($name, $this->getDb())->delete();
                }
            }
        }

        parent::tearDown();
        $this->removeFixtures();
    }

    private function setUpHostProperties(): void
    {
        $this->setUpProperty([
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 4,
        ]);
        $this->setUpProperty([
            'source_expression' => '${command}',
            'destination_field' => 'check_command',
            'priority'          => 3,
        ]);
        $this->setUpProperty([
            'source_expression' => '${value}',
            'destination_field' => 'vars.' . $this->fx('warning'),
            'priority'          => 2,
        ]);
        $this->setUpProperty([
            'source_expression' => '${note}',
            'destination_field' => 'notes',
            'priority'          => 1,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function row(string $command, string $value, string $note): array
    {
        return ['host' => 'SYNCTEST_web01', 'command' => $this->fx($command), 'value' => $value, 'note' => $note];
    }

    /**
     * Run another import that the sync picks up as the latest one
     *
     * Import runs are ordered by their start time, which has a resolution of one
     * second, so the earlier runs of this test move an hour into the past.
     *
     * @param array<int, array<string, string>> $rows
     */
    private function runNextImport(array $rows): void
    {
        $dba = $this->getDb()->getDbAdapter();
        foreach ($dba->fetchPairs($dba->select()->from('import_run', ['id', 'start_time'])) as $id => $startTime) {
            $dba->update(
                'import_run',
                ['start_time' => date('Y-m-d H:i:s', strtotime($startTime) - 3600)],
                $dba->quoteInto('id = ?', (int) $id)
            );
        }

        $this->runImport($rows);
    }

    private function resync(): void
    {
        $this->rule = SyncRule::load($this->rule->get('rule_name'), $this->getDb());
        $this->sync = new Sync($this->rule);
    }
}
