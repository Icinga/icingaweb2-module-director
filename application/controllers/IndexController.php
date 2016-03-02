<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class IndexController extends ActionController
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->getTabs()->add('overview', array(
            'url' => $this->getRequest()->getUrl(),
            'label' => $this->translate('Overview')
        ))->activate('overview');

        if (! $this->Config()->get('db', 'resource')) {
            $this->view->errorMessage = sprintf(
                $this->translate('No database resource has been configured yet. Please %s to complete your config'),
                $this->view->qlink($this->translate('click here'), 'director/settings')
            );
            return;
        }

        $this->view->hasDeploymentEndpoint = $this->db()->hasDeploymentEndpoint();
        $this->view->stats = $this->db()->getObjectSummary();
        $this->view->undeployedActivities = $this->db()->countActivitiesSinceLastDeployedConfig();
        if ((int) $this->view->stats['apiuser']->cnt_total === 0) {
            $this->view->form = $this->loadForm('kickstart')->setDb($this->db)->handleRequest();
        }
    }
}
