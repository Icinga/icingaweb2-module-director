<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Deployment\DeploymentStatus;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;

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
     * Does nothing if config didn't change unless you provide
     * the --force parameter
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

        if ($api->dumpConfig($config, $db)) {
            printf("Config '%s' has been deployed\n", $checksum);
        } else {
            $this->fail(
                sprintf("Failed to deploy config '%s'\n", $checksum)
            );
        }
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

        printf(json_encode($result));
    }
}
