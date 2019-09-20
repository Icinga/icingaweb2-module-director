<?php

namespace Icinga\Module\Director\Db;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;

class Housekeeping
{
    /**
     * @var Db
     */
    protected $connection;

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    protected $db;

    /**
     * @var int
     */
    protected $version;

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getTaskSummary()
    {
        $summary = array();
        foreach ($this->listTasks() as $name => $title) {
            $func = 'count' . ucfirst($name);
            $summary[$name] = (object) array(
                'name'  => $name,
                'title' => $title,
                'count' => $this->$func()
            );
        }

        return $summary;
    }

    public function listTasks()
    {
        return array(
            'oldUndeployedConfigs'       => N_('Undeployed configurations'),
            'unusedFiles'                => N_('Unused rendered files'),
            'unlinkedImportedRowSets'    => N_('Unlinked imported row sets'),
            'unlinkedImportedRows'       => N_('Unlinked imported rows'),
            'unlinkedImportedProperties' => N_('Unlinked imported properties'),
            'resolveCache'               => N_('(Host) group resolve cache'),
        );
    }

    public function getPendingTaskSummary()
    {
        return array_filter(
            $this->getTaskSummary(),
            function ($task) {
                return $task->count > 0;
            }
        );
    }

    public function hasPendingTasks()
    {
        return count($this->getPendingTaskSummary()) > 0;
    }

    public function runAllTasks()
    {
        $result = array();

        foreach ($this->listTasks() as $name => $task) {
            $this->runTask($name);
        }

        return $this;
    }

    public function runTask($name)
    {
        $func = 'wipe' . ucfirst($name);
        if (!method_exists($this, $func)) {
            throw new NotFoundError(
                'There is no such task: %s',
                $name
            );
        }

        return $this->$func();
    }

    public function countOldUndeployedConfigs()
    {
        $conn = $this->connection;
        $lastActivity = $conn->getLastActivityChecksum();

        $sql = 'SELECT COUNT(*) FROM director_generated_config c'
             . '  LEFT JOIN director_deployment_log d ON c.checksum = d.config_checksum'
             . ' WHERE d.config_checksum IS NULL'
             . '   AND ? != ' . $conn->dbHexFunc('c.last_activity_checksum');

        return $this->db->fetchOne($sql, $lastActivity);
    }

    public function wipeOldUndeployedConfigs()
    {
        $conn = $this->connection;
        $lastActivity = $conn->getLastActivityChecksum();

        if ($this->connection->isPgsql()) {
            $sql = 'DELETE FROM director_generated_config'
                . ' USING director_generated_config AS c'
                . ' LEFT JOIN director_deployment_log d ON c.checksum = d.config_checksum'
                . ' WHERE director_generated_config.checksum = c.checksum'
                . '   AND d.config_checksum IS NULL'
                . '   AND ? != ' . $conn->dbHexFunc('c.last_activity_checksum');
        } else {
            $sql = 'DELETE c.* FROM director_generated_config c'
                . ' LEFT JOIN director_deployment_log d ON c.checksum = d.config_checksum'
                . ' WHERE d.config_checksum IS NULL'
                . '   AND ? != ' . $conn->dbHexFunc('c.last_activity_checksum');
        }

        return $this->db->query($sql, $lastActivity);
    }

    public function countUnusedFiles()
    {
        $sql = 'SELECT COUNT(*) FROM director_generated_file f'
             . ' LEFT JOIN director_generated_config_file cf ON f.checksum = cf.file_checksum'
             . ' WHERE cf.file_checksum IS NULL';

        return $this->db->fetchOne($sql);
    }

    public function wipeUnusedFiles()
    {
        if ($this->connection->isPgsql()) {
            $sql = 'DELETE FROM director_generated_file'
                . ' USING director_generated_file AS f'
                . ' LEFT JOIN director_generated_config_file cf ON f.checksum = cf.file_checksum'
                . ' WHERE director_generated_file.checksum = f.checksum'
                . ' AND cf.file_checksum IS NULL';
        } else {
            $sql = 'DELETE f FROM director_generated_file f'
                . ' LEFT JOIN director_generated_config_file cf ON f.checksum = cf.file_checksum'
                . ' WHERE cf.file_checksum IS NULL';
        }

        return $this->db->exec($sql);
    }

    public function countUnlinkedImportedRowSets()
    {
        $sql = 'SELECT COUNT(*) FROM imported_rowset rs LEFT JOIN import_run r'
            . ' ON r.rowset_checksum = rs.checksum WHERE r.id IS NULL';

        return $this->db->fetchOne($sql);
    }

    public function wipeUnlinkedImportedRowSets()
    {
        // This one removes imported_rowset and imported_rowset_row
        // entries no longer used by any historic import<F12>
        if ($this->connection->isPgsql()) {
            $sql = 'DELETE FROM imported_rowset'
                . ' USING  imported_rowset AS rs'
                . ' LEFT JOIN import_run r ON r.rowset_checksum = rs.checksum'
                . ' WHERE imported_rowset.checksum = rs.checksum'
                . ' AND r.id IS NULL';
        } else {
            $sql = 'DELETE rs.* FROM imported_rowset rs'
                . ' LEFT JOIN import_run r ON r.rowset_checksum = rs.checksum'
                . ' WHERE r.id IS NULL';
        }

        return $this->db->exec($sql);
    }

    public function countUnlinkedImportedRows()
    {
        $sql = 'SELECT COUNT(*) FROM imported_row r LEFT JOIN imported_rowset_row rsr'
            . ' ON rsr.row_checksum = r.checksum WHERE rsr.row_checksum IS NULL';

        return $this->db->fetchOne($sql);
    }

    public function wipeUnlinkedImportedRows()
    {
        // This query removes imported_row and imported_row_property columns
        // without related rowset
        if ($this->connection->isPgsql()) {
            $sql = 'DELETE FROM imported_row'
                . ' USING imported_row AS r'
                . ' LEFT JOIN imported_rowset_row rsr ON rsr.row_checksum = r.checksum'
                . ' WHERE imported_row.checksum = r.checksum'
                . ' AND rsr.row_checksum IS NULL';
        } else {
            $sql = 'DELETE r.* FROM imported_row r'
                . ' LEFT JOIN imported_rowset_row rsr ON rsr.row_checksum = r.checksum'
                . ' WHERE rsr.row_checksum IS NULL';
        }

        return $this->db->exec($sql);
    }

    public function countUnlinkedImportedProperties()
    {
        $sql = 'SELECT COUNT(*) FROM imported_property p LEFT JOIN imported_row_property rp'
            . ' ON rp.property_checksum = p.checksum WHERE rp.property_checksum IS NULL';

        return $this->db->fetchOne($sql);
    }

    public function wipeUnlinkedImportedProperties()
    {
        // This query removes unlinked imported properties
        if ($this->connection->isPgsql()) {
            $sql = 'DELETE FROM imported_property'
                . ' USING imported_property AS p'
                . ' LEFT JOIN imported_row_property rp ON rp.property_checksum = p.checksum'
                . ' WHERE imported_property.checksum = p.checksum'
                . ' AND rp.property_checksum IS NULL';
        } else {
            $sql = 'DELETE p.* FROM imported_property p'
                . ' LEFT JOIN imported_row_property rp ON rp.property_checksum = p.checksum'
                . ' WHERE rp.property_checksum IS NULL';
        }

        return $this->db->exec($sql);
    }

    public function countResolveCache()
    {
        $helper = MembershipHousekeeping::instance('host', $this->connection);
        return array_sum($helper->check());
    }

    public function wipeResolveCache()
    {
        $helper = MembershipHousekeeping::instance('host', $this->connection);
        return $helper->update();
    }
}
