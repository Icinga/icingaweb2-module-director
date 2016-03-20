<?php

namespace Icinga\Module\Director\Db;

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
        return array(
            'oldUndeployedConfigs' => (object) array(
                'title' => N_('Undeployed configurations'),
                'count' => $this->countOldUndeployedConfigs()
            ),
            'unusedFiles' => (object) array(
                'title' => N_('Unused rendered files'),
                'count' => $this->countUnusedFiles()
            )
        );
    }

    public function getPendingTaskSummary()
    {
        return array_filter(
            $this->getTaskSummary(),
            function($task) {
                return $task->count > 0;
            }
        );
    }

    public function countOldUndeployedConfigs()
    {
        $sql = 'SELECT COUNT(*) FROM director_generated_config c'
             . ' LEFT JOIN director_deployment_log d ON c.checksum = d.config_checksum'
             . ' WHERE d.config_checksum IS NULL';

        return $this->db->fetchOne($sql);
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
        $sql = 'DELETE f FROM director_generated_file f'
             . ' LEFT JOIN director_generated_config_file cf ON f.checksum = cf.file_checksum'
             . ' WHERE cf.file_checksum IS NULL';

        return $this->db->exec($sql);
    }

    public function wipeUnusedImportedRowsAndProperties()
    {
        $queries = array(
            // This one removes imported_rowset and imported_rowset_row
            // entries no longer used by any historic import
            'DELETE rs.* FROM imported_rowset rs LEFT JOIN import_run r'
            . ' ON r.rowset_checksum = rs.checksum WHERE r.id IS NULL',

            // This query removes imported_row and imported_row_property columns
            // without related rowset
            'DELETE r.* FROM imported_row r LEFT JOIN imported_rowset_row rsr'
            . ' ON rsr.row_checksum = r.checksum WHERE rsr.row_checksum IS NULL',

            // This
            'DELETE p.* FROM imported_property p LEFT JOIN imported_row_property rp'
            . ' ON rp.property_checksum = p.checksum WHERE rp.property_checksum IS NULL'
        );

        $count = 0;
        foreach ($queries as $sql) {
            $count += $this->db->exec($sql);
        }

        return $count;
    }
}
