<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Deployment\ConditionalDeployment;
use Icinga\Module\Director\Deployment\DeploymentGracePeriod;
use Icinga\Module\Director\Deployment\DeploymentStatus;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Import\SyncUtils;

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
     *  [--grace-period <seconds>]
     *
     * OPTIONS
     *
     *   --checksum <checksum>  Optionally deploy a specific configuration
     *   --force                Force a deployment, even when the configuration
     *                          hasn't changed
     *   --wait <seconds>       Optionally wait until Icinga completed it's
     *                          restart
     *   --grace-period <seconds>  Do not deploy if a deployment took place
     *                          less than <seconds> ago
     */
    public function deployAction()
    {
        $db = $this->db();

        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(hex2bin($checksum), $db);
        } else {
            $config = IcingaConfig::generate($db);
            $checksum = $config->getHexChecksum();
        }

        $deployer = new ConditionalDeployment($db, $this->api());
        $deployer->force((bool) $this->params->get('force'));
        if ($graceTime = $this->params->get('grace-period')) {
            $deployer->setGracePeriod(new DeploymentGracePeriod((int) $graceTime, $db));
            if ($this->params->get('force')) {
                fwrite(STDERR, "WARNING: force overrides Grace period\n");
            }
        }
        $deployer->refresh();

        if ($deployment = $deployer->deploy($config)) {
            if ($deployer->hasBeenForced()) {
                echo $deployer->getNoDeploymentReason() . ", deploying anyway\n";
            }
            printf("Config '%s' has been deployed\n", $checksum);
        } else {
            echo $deployer->getNoDeploymentReason() . "\n";
            return;
        }

        if ($timeout = $this->getWaitTime()) {
            $deployed = $deployer->waitForStartupAfterDeploy($deployment, $timeout);
            if ($deployed !== true) {
                $this->fail("Failed to deploy config '%s': %s\n", $checksum, $deployed);
            }
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
        if ($key = $this->params->get('key')) {
            $result = SyncUtils::getSpecificValue($result, $key);
        }

        if (is_string($result)) {
            echo "$result\n";
        } else {
            echo Json::encode($result, JSON_PRETTY_PRINT) . "\n";
        }
    }

    protected function getWaitTime()
    {
        if ($timeout = $this->params->get('wait')) {
            if (!ctype_digit($timeout)) {
                $this->fail("--wait must be the number of seconds to wait'");
            }

            return (int) $timeout;
        }

        return null;
    }
}
