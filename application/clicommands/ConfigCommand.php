<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Deployment\DeploymentStatus;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

/**
 * Generate, show and deploy Icinga 2 configuration
 */
class ConfigCommand extends Command
{
    /**
     * Re-render the current configuration
     */
    public function renderAction()
    {
        $profile = $this->params->shift('profile');
        if ($profile) {
            $this->enableDbProfiler();
        }

        $config = new IcingaConfig($this->db());
        Benchmark::measure('Rendering config');
        if ($config->hasBeenModified()) {
            Benchmark::measure('Config rendered, storing to db');
            $config->store();
            Benchmark::measure('All done');
            $checksum = $config->getHexChecksum();
            printf(
                "New config with checksum %s has been generated\n",
                $checksum
            );
        } else {
            $checksum = $config->getHexChecksum();
            printf(
                "Config with checksum %s already exists\n",
                $checksum
            );
        }

        if ($profile) {
            $this->dumpDbProfile();
        }
    }

    protected function dumpDbProfile()
    {
        $profiler = $this->getDbProfiler();

        $totalTime    = $profiler->getTotalElapsedSecs();
        $queryCount   = $profiler->getTotalNumQueries();
        $longestTime  = 0;
        $longestQuery = null;

        /** @var \Zend_Db_Profiler_Query  $query */
        foreach ($profiler->getQueryProfiles() as $query) {
            echo $query->getQuery() . "\n";
            if ($query->getElapsedSecs() > $longestTime) {
                $longestTime  = $query->getElapsedSecs();
                $longestQuery = $query->getQuery();
            }
        }

        echo 'Executed ' . $queryCount . ' queries in ' . $totalTime . ' seconds' . "\n";
        echo 'Average query length: ' . $totalTime / $queryCount . ' seconds' . "\n";
        echo 'Queries per second: ' . $queryCount / $totalTime . "\n";
        echo 'Longest query length: ' . $longestTime . "\n";
        echo "Longest query: \n" . $longestQuery . "\n";
    }

    protected function getDbProfiler()
    {
        return $this->db()->getDbAdapter()->getProfiler();
    }

    protected function enableDbProfiler()
    {
        return $this->getDbProfiler()->setEnabled(true);
    }

    /**
     * Deploy the current configuration
     *
     * USAGE
     *
     * icingacli director config deploy [--checksum <checksum>] [--force] [--wait <seconds>]
     *
     * OPTIONS
     *
     *   --checksum <checksum>  Optionally deploy a specific configuration
     *   --force                Force a deployment, even when the configuration hasn't
     *                          changed
     *   --wait <seconds>       Optionally wait until Icinga completed it's restart
     */
    public function deployAction()
    {
        $api = $this->api();
        $db = $this->db();

        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(hex2bin($checksum), $db);
        } else {
            $config = IcingaConfig::generate($db);
            $checksum = $config->getHexChecksum();
        }

        $api->wipeInactiveStages($db);
        $current = $api->getActiveChecksum($db);
        if ($current === $checksum) {
            if ($this->params->get('force')) {
                echo "Config matches active stage, deploying anyway\n";
            } else {
                echo "Config matches active stage, nothing to do\n";

                return;
            }
        }

        $deploymentLog = $api->dumpConfig($config, $db);
        if (! $deploymentLog) {
            $this->fail("Failed to deploy config '%s'", $checksum);
        }
        if ($timeout = $this->params->get('wait')) {
            if (! ctype_digit($timeout)) {
                $this->fail("--wait must be the number of seconds to wait'");
            }
            $deployed = $this->waitForStartupAfterDeploy($deploymentLog, $timeout);
            if ($deployed !== true) {
                $this->fail("Failed to deploy config '%s': %s\n", $checksum, $deployed);
            }
        }
        printf("Config '%s' has been deployed\n", $checksum);
    }

    /**
     * Checks the deployments status
     */
    public function deploymentstatusAction()
    {
        $db = $this->db();
        $api = $this->api();
        $status = new DeploymentStatus($db, $api);
        $result = $status->getDeploymentStatus($this->params->get('configs'), $this->params->get('activities'));
        if ($key = $this->params->get('key')) {
            $result = SyncUtils::getSpecificValue($result, $key);
        }

        if (is_string($result)) {
            echo "$result\n";
        } else {
            echo Json::encode($result, JSON_PRETTY_PRINT) . "\n";
        }
    }

    private function waitForStartupAfterDeploy($deploymentLog, $timeout)
    {
        $startTime = time();
        while ((time() - $startTime) <= $timeout) {
            $deploymentFromDB = DirectorDeploymentLog::load($deploymentLog->getId(), $this->db());
            $stageCollected = $deploymentFromDB->get('stage_collected');
            if ($stageCollected === null) {
                usleep(500000);
                continue;
            }
            if ($stageCollected === 'n') {
                return 'stage has not been collected';
            }
            if ($deploymentFromDB->get('startup_succeeded') === 'y') {
                return true;
            }
            return 'deployment failed during startup';
        }
        return 'deployment timed out';
    }
}
