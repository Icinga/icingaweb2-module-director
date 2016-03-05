<?php

namespace Icinga\Module\Director\Db;

use DirectoryIterator;
use Exception;
use Icinga\Module\Director\Db;

class Migrations
{
    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    /**
     * @var Db
     */
    protected $connection;

    protected $migrationsDir;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getLastMigrationNumber()
    {
        try {
            $query = $this->db->select()->from(
                array('m' => 'director_schema_migration'),
                array('schema_version' => 'MAX(schema_version)')
            );

            return (int) $this->db->fetchOne($query);
        } catch (Exception $e) {
            return 0;
        }
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

    public function applyPendingMigrations()
    {
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

        return $this->listMigrationsAfter($this->getLastMigrationNumber());
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
            $filename = sprintf(
                '%s/upgrade_%d.sql',
                $this->getMigrationsDir(),
                $version
            );
        }

        return file_get_contents($filename);
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
            $this->migrationsDir = dirname(dirname(dirname(__DIR__)))
                . '/schema/'
                . $this->connection->getDbType()
                . '-migrations';
        }

        return $this->migrationsDir;
    }

    protected function getFullSchemaFile()
    {
        return dirname(dirname(dirname(__DIR__)))
            . '/schema/'
            . $this->connection->getDbType()
            . '.sql';
    }
}
