<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
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

            $this->fetchSyncState();
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
