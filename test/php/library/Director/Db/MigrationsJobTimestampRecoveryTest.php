<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Db;

use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Test\BaseTestCase;
use RuntimeException;

class MigrationsJobTimestampRecoveryTest extends BaseTestCase
{
    private function simulateSchema(array $columns, bool $migrationRecorded, int $lastVersion = 193): Migrations
    {
        return new class ($this->getDb(), $columns, $migrationRecorded, $lastVersion) extends Migrations {
            private array $columns;

            private bool $migrationRecorded;

            private int $lastVersion;

            public function __construct($db, array $columns, bool $migrationRecorded, int $lastVersion)
            {
                parent::__construct($db);
                $this->columns = $columns;
                $this->migrationRecorded = $migrationRecorded;
                $this->lastVersion = $lastVersion;
            }

            public function getLastMigrationNumber()
            {
                return $this->lastVersion;
            }

            public function listAllMigrations()
            {
                return [189, 190, 191, 192, 193];
            }

            protected function getJobTimestampColumns(): array
            {
                $result = [];
                foreach ($this->columns as $column => $type) {
                    $result[$column] = ['DATA_TYPE' => $type];
                }

                return $result;
            }

            protected function isMigrationRecorded(int $version): bool
            {
                return $version === 189 && $this->migrationRecorded;
            }
        };
    }

    public function testMissing189IsPendingEvenIfLaterMigrationsAreMarkedApplied(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $migrations = $this->simulateSchema([
            'ts_last_attempt' => 'timestamp',
            'ts_last_error' => 'timestamp',
        ], false);
        $this->assertSame([189], $migrations->listPendingMigrations());
    }

    public function testAlreadyRecorded189WithWrongColumnTypesMustNotReportHealthy(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $migrations = $this->simulateSchema([
            'ts_last_attempt' => 'timestamp',
            'ts_last_error' => 'timestamp',
        ], true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('director_job');
        $migrations->listPendingMigrations();
    }

    public function testCorrectBigintSchemaNeedsNoRecovery(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $migrations = $this->simulateSchema([
            'ts_last_attempt' => 'bigint',
            'ts_last_error' => 'bigint',
        ], false);
        $this->assertSame([], $migrations->listPendingMigrations());
    }

    public function testMixedOrInterruptedMigrationMustNotBeReappliedAutomatically(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $migrations = $this->simulateSchema([
            'ts_last_attempt' => 'bigint',
            'ts_last_error' => 'timestamp',
            'ts_last_attempt_tmp' => 'bigint',
        ], false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('director_job');
        $migrations->listPendingMigrations();
    }

    public function testOlderSchemaStillUsesNormalMigrationSequence(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $migrations = $this->simulateSchema([
            'ts_last_attempt' => 'timestamp',
            'ts_last_error' => 'timestamp',
        ], false, 188);
        $this->assertSame([189, 190, 191, 192, 193], $migrations->listPendingMigrations());
    }
}
