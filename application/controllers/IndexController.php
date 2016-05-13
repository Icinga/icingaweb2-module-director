<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Web\Controller\ActionController;

class IndexController extends ActionController
{
    public function indexAction()
    {
        if ($this->getRequest()->isGet()) {
            $this->setAutorefreshInterval(10);
        }

        if (! $this->Config()->get('db', 'resource')
            || !$this->fetchStats()
            || !$this->hasDeploymentEndpoint()
        ) {
            $this->getTabs()->add('overview', array(
                'url' => $this->getRequest()->getUrl(),
                'label' => $this->translate('Configuration')
            ))->activate('overview');
            $this->view->title = $this->translate('Configuration');
            $this->view->form = $this->loadForm('kickstart')->handleRequest();

        } else {
            $this->getTabs()->add('overview', array(
                'url' => $this->getRequest()->getUrl(),
                'label' => $this->translate('Overview')
            ))->activate('overview');

            $migrations = new Migrations($this->db());

            if ($migrations->hasPendingMigrations()) {
                $this->view->migrationsForm = $this
                    ->loadForm('applyMigrations')
                    ->setMigrations($migrations)
                    ->handleRequest();
            }

            try {
                $this->fetchSyncState()
                     ->fetchImportState()
                     ->fetchJobState();
            } catch (Exception $e) {
            }
        }
    }

    protected function fetchSyncState()
    {
        $syncs = SyncRule::loadAll($this->db());
        if (count($syncs) > 0) {
            $state = 'ok';
        } else {
            $state = null;
        }

        foreach ($syncs as $sync) {
            if ($sync->sync_state !== 'in-sync') {
                if ($sync->sync_state === 'failing') {
                    $state = 'critical';
                    break;
                } else {
                    $state = 'warning';
                }
            }
        }

        $this->view->syncState = $state;

        return $this;
    }

    protected function fetchImportState()
    {
        $srcs = ImportSource::loadAll($this->db());
        if (count($srcs) > 0) {
            $state = 'ok';
        } else {
            $state = null;
        }

        foreach ($srcs as $src) {
            if ($src->import_state !== 'in-sync') {
                if ($src->import_state === 'failing') {
                    $state = 'critical';
                    break;
                } else {
                    $state = 'warning';
                }
            }
        }

        $this->view->importState = $state;

        return $this;
    }

    protected function fetchJobState()
    {
        $jobs = DirectorJob::loadAll($this->db());
        if (count($jobs) > 0) {
            $state = 'ok';
        } else {
            $state = null;
        }

        foreach ($jobs as $job) {
            if ($job->isPending()) {
                $state = 'pending';
            } elseif (! $job->lastAttemptSucceeded()) {
                $state = 'critical';
                break;
            }
        }

        $this->view->jobState = $state;

        return $this;
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

    protected function fetchStats()
    {
        try {
            $this->view->stats = $this->db()->getObjectSummary();
            $this->view->undeployedActivities = $this->db()->countActivitiesSinceLastDeployedConfig();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
