<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
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
        }
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
