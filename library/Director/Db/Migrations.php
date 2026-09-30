<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db;

use DirectoryIterator;
use Exception;
use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Data\Db\DbConnection;
use RuntimeException;

class Migrations
{
    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /**
     * @var DbConnection
     */
    protected $connection;

    protected $migrationsDir;

    public function __construct(DbConnection $connection)
    {
        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            throw new RuntimeException(
                "PHP version 5.4.x is required for Director >= 1.4.0, you're running %s."
                . ' Please either upgrade PHP or downgrade Icinga Director',
                PHP_VERSION
            );
        }
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getLastMigrationNumber()
    {
        try {
            $query = $this->db->select()->from(
                array('m' => $this->getTableName()),
                array('schema_version' => 'MAX(schema_version)')
            );

            return (int) $this->db->fetchOne($query);
        } catch (Exception $e) {
            return 0;
        }
    }

    protected function getTableName()
    {
        return $this->getModuleName() . '_schema_migration';
    }

    public function hasSchema()
    {
        return $this->listPendingMigrations() !== array(0);
    }

    public function hasPendingMigrations()
    {
        return $this->countPendingMigrations() > 0;
    }

    public function countPendingMigrations()
    {
        return count($this->listPendingMigrations());
    }

    /**
     * @return Migration[]
     */
    public function getPendingMigrations()
    {
        $migrations = array();
        foreach ($this->listPendingMigrations() as $version) {
            $migrations[] = new Migration(
                $version,
                $this->loadMigrationFile($version)
            );
        }

        return $migrations;
    }

    /**
     * @return $this
     */
    public function applyPendingMigrations()
    {
        // Ensure we have enough time to migrate
        ini_set('max_execution_time', '0');

        foreach ($this->getPendingMigrations() as $migration) {
            $migration->apply($this->connection);
        }

        return $this;
    }

    public function listPendingMigrations()
    {
        $lastMigration = $this->getLastMigrationNumber();
        if ($lastMigration === 0) {
            return array(0);
        }

        $pending = $this->listMigrationsAfter($lastMigration);

        // Migration 189 changed job timestamps from DATETIME/TIMESTAMP to
        // epoch milliseconds. A DB imported from another package or with a
        // non-contiguous migration history can have a later recorded version
        // while this migration is still missing. MAX(schema_version) alone
        // cannot distinguish that state from a correctly upgraded schema.
        if ($lastMigration >= 189 && $this->needsJobTimestampMigration()) {
            array_unshift($pending, 189);
        }

        return $pending;
    }

    /**
     * Determine whether upgrade_189.sql may safely be applied to a schema
     * whose recorded migration version has already moved beyond 189.
     */
    protected function needsJobTimestampMigration(): bool
    {
        $columns = $this->getJobTimestampColumns();
        if (! isset($columns['ts_last_attempt'], $columns['ts_last_error'])) {
            throw new RuntimeException('director_job is missing one or both job timestamp columns');
        }

        $attemptType = strtolower($columns['ts_last_attempt']['DATA_TYPE']);
        $errorType = strtolower($columns['ts_last_error']['DATA_TYPE']);
        if ($attemptType === 'bigint' && $errorType === 'bigint') {
            return false;
        }

        // Reapplying the old migration to a partly converted table can drop
        // data. A recorded migration 189 with old columns is likewise schema
        // drift, not a pending migration. Neither case is safe to auto-repair.
        $isLegacy = strpos($attemptType, 'timestamp') === 0
            && strpos($errorType, 'timestamp') === 0;
        if (
            ! $isLegacy
            || isset($columns['ts_last_attempt_tmp'], $columns['ts_last_error_tmp'])
            || $this->isMigrationRecorded(189)
        ) {
            throw new RuntimeException(
                'director_job timestamp columns do not match migration 189. '
                . 'Check the column types and director_schema_migration history; '
                . 'restore or repair the schema from a database backup before running Director jobs.'
            );
        }

        return true;
    }

    protected function getJobTimestampColumns(): array
    {
        return $this->db->describeTable('director_job');
    }

    protected function isMigrationRecorded(int $version): bool
    {
        return (bool) $this->db->fetchOne(
            $this->db->select()
                ->from($this->getTableName(), ['schema_version'])
                ->where('schema_version = ?', $version)
        );
    }

    public function listAllMigrations()
    {
        $dir = $this->getMigrationsDir();
        if (! is_readable($dir)) {
            return array();
        }

        $versions = array();

        foreach (new DirectoryIterator($this->getMigrationsDir()) as $file) {
            if ($file->isDot()) {
                continue;
            }

            $filename = $file->getFilename();
            if (preg_match('/^upgrade_(\d+)\.sql$/', $filename, $match)) {
                $versions[] = $match[1];
            }
        }

        sort($versions);

        return $versions;
    }

    public function loadMigrationFile($version)
    {
        if ($version === 0) {
            $filename = $this->getFullSchemaFile();
        } else {
            $filename = $this->getMigrationFileName($version);
        }

        return file_get_contents($filename);
    }

    public function hasBeenDowngraded()
    {
        return ! $this->hasMigrationFile($this->getLastMigrationNumber());
    }

    public function hasMigrationFile($version)
    {
        return \file_exists($this->getMigrationFileName($version));
    }

    protected function getMigrationFileName($version)
    {
        return sprintf(
            '%s/upgrade_%d.sql',
            $this->getMigrationsDir(),
            $version
        );
    }

    protected function listMigrationsAfter($version)
    {
        $filtered = array();
        foreach ($this->listAllMigrations() as $available) {
            if ($available > $version) {
                $filtered[] = $available;
            }
        }

        return $filtered;
    }

    protected function getMigrationsDir()
    {
        if ($this->migrationsDir === null) {
            $this->migrationsDir = $this->getSchemaDir(
                $this->connection->getDbType() . '-migrations'
            );
        }

        return $this->migrationsDir;
    }

    protected function getFullSchemaFile()
    {
        return $this->getSchemaDir(
            $this->connection->getDbType() . '.sql'
        );
    }

    protected function getSchemaDir($sub = null)
    {
        try {
            $dir = $this->getModuleDir('/schema');
        } catch (ProgrammingError $e) {
            throw new RuntimeException(
                'Unable to detect the schema directory for this module',
                0,
                $e
            );
        }
        if ($sub === null) {
            return $dir;
        } else {
            return $dir . '/' . ltrim($sub, '/');
        }
    }

    /**
     * @param string $sub
     * @return string
     * @throws ProgrammingError
     */
    protected function getModuleDir($sub = '')
    {
        return Icinga::app()->getModuleManager()->getModuleDir(
            $this->getModuleName(),
            $sub
        );
    }

    protected function getModuleName()
    {
        return $this->getModuleNameForObject($this);
    }

    protected function getModuleNameForObject($object)
    {
        $class = get_class($object);
        // Hint: Icinga\Module\ -> 14 chars
        return lcfirst(substr($class, 14, strpos($class, '\\', 15) - 14));
    }
}
