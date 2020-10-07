<?php

namespace Icinga\Module\Director\Deployment;

use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;

class DeploymentStatus
{
    protected $db;

    protected $api;

    public function __construct(Db $db, CoreApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    public function getConfigChecksumForStageName($stageName)
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            array('l' => 'director_deployment_log'),
            array('checksum' => $this->db->dbHexFunc('l.config_checksum'))
        )->where('l.stage_name = ?', $stageName);

        return $db->fetchOne($query);
    }
}
