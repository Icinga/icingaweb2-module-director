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
            'oldUndeployedConfigs' => array(
                'title' => N_('Undeployed configurations'),
                'count' => $this->countOldUndeployedConfigs()
            ),
            'unusedFiles' => array(
                'title' => N_('Unused rendered files'),
                'count' => $this->countUnusedFiles()
            )
        );
    }

    public function getPendingTasks()
    {
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
}
