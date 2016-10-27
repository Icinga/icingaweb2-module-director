<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;

class EndpointObjectDashlet extends Dashlet
{
    protected $icon = 'cloud';

    protected $requiredStats = array('endpoint');

    protected $hasDeploymentEndpoint;

    public function getTitle()
    {
        return $this->translate('Endpoints');
    }

    public function getUrl()
    {
        return 'director/endpoints';
    }

    protected function hasDeploymentEndpoint()
    {
        if ($this->hasDeploymentEndpoint === null) {
            try {
                $this->hasDeploymentEndpoint = $this->db->hasDeploymentEndpoint();
            } catch (Exception $e) {
                return false;
            }
        }

        return $this->hasDeploymentEndpoint;
    }

    public function listCssClasses()
    {
        if (! $this->hasDeploymentEndpoint()) {
            return 'state-critical';
        }
    }

    public function getSummary()
    {
        $msg = parent::getSummary();
        if (! $this->hasDeploymentEndpoint()) {
            $msg .= '. ' . $this->translate(
                'None could be used for deployments right now'
            );
        }

        return $msg;
    }
}
