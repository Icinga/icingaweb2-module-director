<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;

class IndexController extends DashboardController
{
    public function indexAction()
    {
        $this->view->dashboards = array();

        $this->setViewScript('dashboard/index');

        if ($this->Config()->get('db', 'resource')) {
            $migrations = new Migrations($this->db());

            if ($migrations->hasSchema()) {
                if (!$this->hasDeploymentEndpoint()) {
                    $this->showKickstartForm();
                }
            } else {
                $this->showKickstartForm();
                return;
            }

            if ($migrations->hasPendingMigrations()) {
                $this->view->form = $this
                    ->loadForm('applyMigrations')
                    ->setMigrations($migrations)
                    ->handleRequest();
            }

            parent::indexAction();
        } else {
            $this->showKickstartForm();
        }
    }

    protected function showKickstartForm()
    {
        $this->singleTab($this->translate('Kickstart'));
        $this->view->form = $this->loadForm('kickstart')->handleRequest();
    }

    protected function hasDeploymentEndpoint()
    {
        try {
            $this->view->hasDeploymentEndpoint = $this->db()->hasDeploymentEndpoint();
        } catch (Exception $e) {
            return false;
        }

        return $this->view->hasDeploymentEndpoint;
    }
}
