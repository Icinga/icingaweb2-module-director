<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Util;

class Director_DeploymentController extends ActionController
{
    public function showAction()
    {
        $deploymentId = $this->params->get('id');
        $this->view->deployment = $deployment = DirectorDeploymentLog::load(
            $deploymentId,
            $this->db()
        );

        $tabs = $this->getTabs()->add('deployment', array(
            'label' => $this->translate('Deployment'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('deployment');

        if ($deployment->config_checksum !== null) {
            $tabs->add('config', array(
                'label'     => $this->translate('Config'),
                'url'       => 'director/config/show',
                'urlParams' => array(
                    'checksum'      => Util::binary2hex($deployment->config_checksum),
                    'deployment_id' => $deploymentId
                )
            ));
        }
    }
}
