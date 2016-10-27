<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;

class IndexController extends DashboardController
{
    public function indexAction()
    {
        $this->view->dashboards = array();

        if ($this->Config()->get('db', 'resource')) {
            $migrations = new Migrations($this->db());

            if (! $migrations->hasSchema() || !$this->hasDeploymentEndpoint()) {
                $this->showKickstartForm();
            } elseif ($migrations->hasPendingMigrations()) {
                $this->view->form = $this
                    ->loadForm('applyMigrations')
                    ->setMigrations($migrations)
                    ->handleRequest();
                parent::indexAction();
            } else {
                parent::indexAction();
            }
        } else {
            $this->showKickstartForm();
        }

        $this->setViewScript('dashboard/index');
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
