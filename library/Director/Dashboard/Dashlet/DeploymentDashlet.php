<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

class DeploymentDashlet extends Dashlet
{
    protected $icon = 'wrench';

    protected $undeployedActivities;

    protected $lastDeployment;

    public function getTitle()
    {
        return $this->translate('Config Deployment');
    }

    public function hasUndeployedActivities()
    {
        return $this->undeployedActivities() > 0;
    }

    public function undeployedActivities()
    {
        if ($this->undeployedActivities === null) {
            try {
                $this->undeployedActivities = $this->db
                    ->countActivitiesSinceLastDeployedConfig();
            } catch (Exception $e) {
                $this->undeployedActivities = 0;
            }
        }

        return $this->undeployedActivities;
    }

    public function lastDeploymentFailed()
    {
        return ! $this->lastDeployment()->succeeded();
    }

    public function lastDeploymentPending()
    {
        return $this->lastDeployment()->isPending();
    }

    public function listCssClasses()
    {
        try {
            if ($this->lastDeploymentFailed()) {
                return array('state-critical');
            } elseif ($this->lastDeploymentPending()) {
                return array('state-pending');
            } elseif ($this->hasUndeployedActivities()) {
                return array('state-warning');
            } else {
                return array('state-ok');
            }
        } catch (Exception $e) {
            return null;
        }
    }

    protected function lastDeployment()
    {
        if ($this->lastDeployment === null) {
            $this->lastDeployment = DirectorDeploymentLog::loadLatest($this->db);
        }

        return $this->lastDeployment;
    }

    public function getSummary()
    {
        $msgs = array();
        $cnt = $this->undeployedActivities();

        try {
            if ($this->lastDeploymentFailed()) {
                $msgs[] = $this->translate('The last deployment did not succeed');
            } elseif ($this->lastDeploymentPending()) {
                $msgs[] = $this->translate('The last deployment is currently pending');
            }
        } catch (Exception $e) {
        }

        if ($cnt === 0) {
            $msgs[] = $this->translate('There are no pending changes');
        } else {
            $msgs[] = sprintf(
                $this->translate(
                    'A total of %d config changes happened since your last'
                    . ' deployed config has been rendered'
                ),
                $cnt
            );
        }

        return implode('. ', $msgs) . '.';
    }

    public function getUrl()
    {
        return 'director/config/deployments';
    }

    public function listRequiredPermissions()
    {
        return [Permission::DEPLOY];
    }
}
