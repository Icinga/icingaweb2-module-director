<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;
use Icinga\Module\Director\Auth\Permission;

class EndpointObjectDashlet extends Dashlet
{
    protected $icon = 'cloud';

    protected $requiredStats = ['endpoint'];

    protected $hasDeploymentEndpoint;

    public function getTitle()
    {
        return $this->translate('Endpoints');
    }

    public function getUrl()
    {
        return 'director/endpoints';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
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

        return null;
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
