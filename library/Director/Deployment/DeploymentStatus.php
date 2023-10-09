<?php

namespace Icinga\Module\Director\Deployment;

use Exception;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

class DeploymentStatus
{
    protected $db;

    protected $api;

    public function __construct(Db $db, CoreApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    public function getDeploymentStatus($configs = null, $activities = null)
    {
        try {
            if (DirectorDeploymentLog::hasUncollected($this->db)) {
                $this->api->collectLogFiles($this->db);
            }
        } catch (Exception $e) {
            // Ignore eventual issues while talking to Icinga
        }

        $activeConfiguration = null;
        $lastActivityLogChecksum = null;
        $configChecksum = null;
        if ($stageName = $this->api->getActiveStageName()) {
            $activityLogChecksum = DirectorDeploymentLog::getRelatedToActiveStage($this->api, $this->db);
            if ($activityLogChecksum === null) {
                $activeConfiguration = [
                    'stage_name' => $stageName,
                    'config'   => null,
                    'activity' => null
                ];
            } else {
                $lastActivityLogChecksum = bin2hex($activityLogChecksum->get('last_activity_checksum'));
                $configChecksum = $this->getConfigChecksumForStageName($stageName);
                $activeConfiguration = [
                    'stage_name' => $stageName,
                    'config'   => ($configChecksum) ? : null,
                    'activity' => $lastActivityLogChecksum
                ];
            }
        }
        $result = [
            'active_configuration' => (object) $activeConfiguration,
        ];

        if ($configs) {
            $result['configs'] = (object) $this->getDeploymentStatusForConfigChecksums(
                explode(',', $configs),
                $configChecksum
            );
        }

        if ($activities) {
            $result['activities'] = (object) $this->getDeploymentStatusForActivityLogChecksums(
                explode(',', $activities),
                $lastActivityLogChecksum
            );
        }
        return (object) $result;
    }

    public function getConfigChecksumForStageName($stageName)
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            ['l' => 'director_deployment_log'],
            ['checksum' => $this->db->dbHexFunc('l.config_checksum')]
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
            $binaryConfigChecksums[$singleConfigChecksum] = $this->db->quoteBinary(hex2bin($singleConfigChecksum));
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
                        ['g' => 'director_generated_config'],
                        ['checksum' => 'g.checksum']
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
                    ['a' => 'director_activity_log'],
                    ['id' => 'a.id']
                )->where('a.checksum = ?', $this->db->quoteBinary(hex2bin($singleActivityLogChecksum)));
                if ($singleActivityLogData = $db->fetchOne($generatedConfigQuery)) {
                    if ($lastDeploymentActivityLogId = $this->db->getLastDeploymentActivityLogId()) {
                        if ((int) $singleActivityLogData > $lastDeploymentActivityLogId) {
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
     * @param array $binaryConfigChecksums
     * @return array
     */
    public function getDeployedConfigs(array $binaryConfigChecksums)
    {
        $db = $this->db->getDbAdapter();
        $deploymentLogQuery = $db->select()->from(['l' => 'director_deployment_log'], [
            'checksum' => $this->db->dbHexFunc('l.config_checksum'),
            'deployed' => 'l.startup_succeeded'
        ])->where('l.config_checksum IN (?)', $binaryConfigChecksums);
        return $db->fetchPairs($deploymentLogQuery);
    }
}
