<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Forms\ApplyMigrationsForm;
use Icinga\Module\Director\Forms\KickstartForm;
use ipl\Html\Html;

class IndexController extends DashboardController
{
    protected $hasDeploymentEndpoint;

    public function indexAction()
    {
        if ($this->Config()->get('db', 'resource')) {
            $migrations = new Migrations($this->db());

            if ($migrations->hasSchema()) {
                if (!$this->hasDeploymentEndpoint()) {
                    $this->showKickstartForm();
                }
            }

            if ($migrations->hasPendingMigrations()) {
                $this->content()->prepend(
                    ApplyMigrationsForm::load()
                        ->setMigrations($migrations)
                        ->handleRequest()
                );
            } elseif ($migrations->hasBeenDowngraded()) {
                $this->content()->add(Hint::warning(sprintf($this->translate(
                    'Your DB schema (migration #%d) is newer than your code base.'
                    . ' Downgrading Icinga Director is not supported and might'
                    . ' lead to unexpected problems.'
                ), $migrations->getLastMigrationNumber())));
            }

            if ($migrations->hasSchema()) {
                parent::indexAction();
            } else {
                $this->addTitle(sprintf(
                    $this->translate('Icinga Director Setup: %s'),
                    $this->translate('Create Schema')
                ));
                $this->addSingleTab('Setup');
            }
        } else {
            $this->addTitle(sprintf(
                $this->translate('Icinga Director Setup: %s'),
                $this->translate('Choose DB Resource')
            ));
            $this->addSingleTab('Setup');
            $this->showKickstartForm();
        }
    }

    protected function showKickstartForm()
    {
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
