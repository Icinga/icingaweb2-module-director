<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Db;

use Icinga\Module\Director\Db\Migration;
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

    public function testExistingMigrationConvertsLegacyMysqlTimestampsWithoutLosingValues(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $connection = $this->getDb();
        if ($connection->getDbType() !== 'mysql') {
            $this->markTestSkipped('The reported issue concerns a MySQL/MariaDB schema');
        }

        // TEMPORARY tables shadow the real names only on this test connection.
        // Replay the existing upgrade rather than maintaining a second SQL copy.
        $db = $connection->getDbAdapter();
        $db->exec(
            'CREATE TEMPORARY TABLE director_job ('
            . 'id INT PRIMARY KEY, '
            . 'ts_last_attempt TIMESTAMP NULL DEFAULT NULL, '
            . 'ts_last_error TIMESTAMP NULL DEFAULT NULL)'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE director_schema_migration ('
            . 'schema_version INT PRIMARY KEY, migration_time DATETIME NOT NULL)'
        );

        try {
            $db->exec(
                "INSERT INTO director_job (id, ts_last_attempt, ts_last_error)"
                . " VALUES (1, '2026-07-28 08:37:22', NULL)"
            );
            $expected = (int) $db->fetchOne("SELECT UNIX_TIMESTAMP('2026-07-28 08:37:22') * 1000");
            $migrations = new Migrations($connection);
            (new Migration(189, $migrations->loadMigrationFile(189)))->apply($connection);

            $columns = $db->describeTable('director_job');
            $this->assertSame('bigint', strtolower($columns['ts_last_attempt']['DATA_TYPE']));
            $this->assertSame('bigint', strtolower($columns['ts_last_error']['DATA_TYPE']));
            $job = $db->fetchRow('SELECT ts_last_attempt, ts_last_error FROM director_job WHERE id = 1');
            $this->assertSame($expected, (int) $job->ts_last_attempt);
            $this->assertNull($job->ts_last_error);
            $this->assertSame(
                '189',
                (string) $db->fetchOne('SELECT schema_version FROM director_schema_migration')
            );
        } finally {
            $db->exec('DROP TEMPORARY TABLE IF EXISTS director_job');
            $db->exec('DROP TEMPORARY TABLE IF EXISTS director_schema_migration');
        }
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
