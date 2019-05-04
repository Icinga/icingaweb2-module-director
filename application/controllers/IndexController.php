<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Forms\ApplyMigrationsForm;
use Icinga\Module\Director\Forms\KickstartForm;

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
            }

            if ($migrations->hasPendingMigrations()) {
                $this->content()->prepend(
                    ApplyMigrationsForm::load()
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

        $form = KickstartForm::load();
        if ($name = $this->getPreferredDbResourceName()) {
            $form->setDbResourceName($name);
        }
        $this->content()->prepend($form->handleRequest());
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
