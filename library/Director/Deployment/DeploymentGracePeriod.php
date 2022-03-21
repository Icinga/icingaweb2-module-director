<?php

namespace Icinga\Module\Director\Deployment;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

class DeploymentGracePeriod
{
    /** @var int */
    protected $graceTimeSeconds;

    /** @var Db */
    protected $db;

    /**
     * @param int $graceTimeSeconds
     * @param Db $db
     */
    public function __construct($graceTimeSeconds, Db $db)
    {
        $this->graceTimeSeconds = $graceTimeSeconds;
        $this->db = $db;
    }

    /**
     * Whether we're still within a grace period
     * @return bool
     */
    public function isActive()
    {
        if ($deployment = $this->lastDeployment()) {
            return $deployment->getDeploymentTimestamp() > $this->getGracePeriodStart();
        }

        return false;
    }

    protected function getGracePeriodStart()
    {
        return time() - $this->graceTimeSeconds;
    }

    public function getRemainingGraceTime()
    {
        if ($this->isActive()) {
            if ($deployment = $this->lastDeployment()) {
                return $deployment->getDeploymentTimestamp() - $this->getGracePeriodStart();
            } else {
                return null;
            }
        }

        return 0;
    }

    protected function lastDeployment()
    {
        return DirectorDeploymentLog::optionalLatest($this->db);
    }
}
