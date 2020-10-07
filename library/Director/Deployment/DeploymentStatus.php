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

    public function getDeploymentStatusForConfigChecksums($configChecksums, $activeConfigChecksum)
    {
        $db = $this->db->getDbAdapter();
        $results = array_combine($configChecksums, array_map(function () {
            return 'unknown';
        }, $configChecksums));
        $binaryConfigChecksums = [];
        foreach ($configChecksums as $singleConfigChecksum) {
            $binaryConfigChecksums[$singleConfigChecksum] = hex2bin($singleConfigChecksum);
        }
        $deployedConfigs = $this->getDeployedConfigs(array_values($binaryConfigChecksums));

        foreach ($results as $singleChecksum => &$status) {
            // active if it's equal to the provided active
            if ($singleChecksum === $activeConfigChecksum) {
                $status = 'active';
            } else {
                if (isset($deployedConfigs[$singleChecksum])) {
                    $status = ($deployedConfigs[$singleChecksum] === 'y') ? 'deployed' : 'failed';
                } else {
                    // check if it's in generated_config table it is undeployed
                    $generatedConfigQuery = $db->select()->from(
                        array('g' => 'director_generated_config'),
                        array('checksum' => 'g.checksum')
                    )->where('g.checksum = ?', $binaryConfigChecksums[$singleChecksum]);
                    if ($db->fetchOne($generatedConfigQuery)) {
                        $status = 'undeployed';
                    }
                }
                // otherwise leave unknown
            }
        }

        return $results;
    }

    public function getDeploymentStatusForActivityLogChecksums($activityLogChecksums, $activeActivityLogChecksum)
    {
        $db = $this->db->getDbAdapter();
        $results = array_combine($activityLogChecksums, array_map(function () {
            return 'unknown';
        }, $activityLogChecksums));

        foreach ($results as $singleActivityLogChecksum => &$status) {
            // active if it's equal to the provided active
            if ($singleActivityLogChecksum === $activeActivityLogChecksum) {
                $status = 'active';
            } else {
                // get last deployed activity id and check if it's less than the passed one
                $generatedConfigQuery = $db->select()->from(
                    array('a' => 'director_activity_log'),
                    array('id' => 'a.id')
                )->where('a.checksum = ?', hex2bin($singleActivityLogChecksum));
                if ($singleActivityLogData = $db->fetchOne($generatedConfigQuery)) {
                    if ($lastDeploymentActivityLogId = $db->getLastDeploymentActivityLogId()) {
                        if ($singleActivityLogData->id > $lastDeploymentActivityLogId) {
                            $status = 'undeployed';
                        } else {
                            $status = 'deployed';
                        }
                    }
                }
            }
        }
        return $results;
    }

    /**
     * @param $db
     * @param array $binaryConfigChecksums
     * @return mixed
     */
    public function getDeployedConfigs(array $binaryConfigChecksums)
    {
        $db = $this->db->getDbAdapter();
        $deploymentLogQuery = $db->select()->from(
            array('l' => 'director_deployment_log'),
            array(
                'checksum' => $this->db->dbHexFunc('l.config_checksum'),
                'deployed' => 'l.startup_succeeded'
            )
        )->where('l.config_checksum IN (?)', $binaryConfigChecksums);
        return $db->fetchPairs($deploymentLogQuery);
    }
}
