<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;

class IndexController extends DashboardController
{
    protected $hasDeploymentEndpoint;

    public function indexAction()
    {
        if ($this->Config()->get('db', 'resource')) {
            $migrations = new Migrations($this->db());

            if ($migrations->hasSchema()) {
                if (!$this->hasDeploymentEndpoint()) {
                    $this->showKickstartForm(false);
                }
            } else {
                $this->showKickstartForm();
                return;
            }

            if ($migrations->hasPendingMigrations()) {
                $this->content()->prepend(
                    $this
                    ->loadForm('applyMigrations')
                    ->setMigrations($migrations)
                    ->handleRequest()
                );
            }

            parent::indexAction();
        } else {
            $this->showKickstartForm();
        }
    }

    protected function showKickstartForm($showTab = true)
    {
        if ($showTab) {
            $this->addSingleTab($this->translate('Kickstart'));
        }

        $this->content()->prepend(
            $this->loadForm('kickstart')->handleRequest()
        );
    }

    protected function hasDeploymentEndpoint()
    {
        try {
            $this->hasDeploymentEndpoint = $this->db()->hasDeploymentEndpoint();
        } catch (Exception $e) {
            return false;
        }

        return $this->hasDeploymentEndpoint;
    }
}
