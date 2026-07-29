<?php

namespace Icinga\Module\Director\Deployment;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class ConditionalDeployment implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Db */
    protected $db;

    /** @var CoreApi */
    protected $api;

    /** @var ?DeploymentGracePeriod */
    protected $gracePeriod = null;

    protected $force = false;

    protected $hasBeenForced = false;

    /** @var ?string */
    protected $noDeploymentReason = null;

    public function __construct(Db $connection, ?CoreApi $api = null)
    {
        $this->setLogger(new NullLogger());
        $this->db = $connection;
        if ($api === null) {
            $this->api = $connection->getDeploymentEndpoint()->api();
        } else {
            $this->api = $api;
        }
        $this->refresh();
    }

    /**
     * @param IcingaConfig $config
     * @return ?DirectorDeploymentLog
     */
    public function deploy(IcingaConfig $config)
    {
        $this->hasBeenForced = false;
        if ($this->shouldDeploy($config)) {
            return $this->reallyDeploy($config);
        } elseif ($this->force) {
            $deployment = $this->reallyDeploy($config);
            $this->hasBeenForced = true;

            return $deployment;
        }

        return null;
    }

    /**
     * @param bool $force
     * @return $this
     */
    public function force($force = true)
    {
        $this->force = $force;
        return $this;
    }

    public function setGracePeriod(DeploymentGracePeriod $gracePeriod)
    {
        $this->gracePeriod = $gracePeriod;
        return $this;
    }

    public function refresh()
    {
        $this->api->collectLogFiles($this->db);
        $this->api->wipeInactiveStages($this->db);
    }

    public function waitForStartupAfterDeploy(DirectorDeploymentLog $deploymentLog, $timeout)
    {
        $startTime = time();
        while ((time() - $startTime) <= $timeout) {
            $deploymentFromDB = DirectorDeploymentLog::load($deploymentLog->getId(), $this->db);
            $stageCollected = $deploymentFromDB->get('stage_collected');
            if ($stageCollected === null) {
                usleep(500000);
                continue;
            }
            if ($stageCollected === 'n') {
                return 'stage has not been collected (Icinga "lost" the deployment)';
            }
            if ($deploymentFromDB->get('startup_succeeded') === 'y') {
                return true;
            }
            return 'deployment failed during startup (usually a Configuration Error)';
        }
        return 'deployment timed out (while waiting for an Icinga restart)';
    }

    /**
     * @return string|null
     */
    public function getNoDeploymentReason()
    {
        return $this->noDeploymentReason;
    }

    public function hasBeenForced()
    {
        return $this->hasBeenForced;
    }

    protected function shouldDeploy(IcingaConfig $config)
    {
        $this->noDeploymentReason = null;
        if ($this->hasNeverDeployed()) {
            return true;
        }

        if ($this->isWithinGracePeriod()) {
            $this->noDeploymentReason = 'Grace period is active';
            return false;
        }

        if ($this->deployedConfigMatches($config)) {
            $this->noDeploymentReason = 'Config matches last deployed one';
            return false;
        }

        if ($this->getActiveChecksum() === $config->getHexChecksum()) {
            $this->noDeploymentReason = 'Config matches active stage';
            return false;
        }

        return true;
    }

    protected function hasNeverDeployed()
    {
        return !DirectorDeploymentLog::hasDeployments($this->db);
    }

    protected function isWithinGracePeriod()
    {
        return $this->gracePeriod && $this->gracePeriod->isActive();
    }

    protected function deployedConfigMatches(IcingaConfig $config)
    {
        if ($deployment = DirectorDeploymentLog::optionalLatest($this->db)) {
            return $deployment->getConfigHexChecksum() === $config->getHexChecksum();
        }

        return false;
    }

    protected function getActiveChecksum()
    {
        return DirectorDeploymentLog::getConfigChecksumForStageName(
            $this->db,
            $this->api->getActiveStageName()
        );
    }

    /**
     * @param IcingaConfig $config
     * @return bool|DirectorDeploymentLog
     * @throws IcingaException
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function reallyDeploy(IcingaConfig $config)
    {
        $checksum = $config->getHexChecksum();
        $this->logger->info(sprintf('Director ConfigJob ready to deploy "%s"', $checksum));
        if ($deployment = $this->api->dumpConfig($config, $this->db)) {
            $this->logger->notice(sprintf('Director ConfigJob deployed config "%s"', $checksum));
            return $deployment;
        } else {
            throw new IcingaException('Failed to deploy config "%s"', $checksum);
        }
    }
}
