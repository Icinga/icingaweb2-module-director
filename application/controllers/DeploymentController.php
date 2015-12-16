<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;

class DeploymentController extends ActionController
{
    public function indexAction()
    {
        $this->view->title = $this->translate('Deployment details');

        $deploymentId = $this->params->get('id');
        $this->view->deployment = $deployment = DirectorDeploymentLog::load(
            $deploymentId,
            $this->db()
        );
        $this->view->config_checksum = Util::binary2hex($deployment->config_checksum);
        $this->view->config = IcingaConfig::load($deployment->config_checksum, $this->db());

        $tabs = $this->getTabs()->add('deployment', array(
            'label' => $this->translate('Deployment'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('deployment');

        if ($deployment->config_checksum !== null) {
            $tabs->add('config', array(
                'label'     => $this->translate('Config'),
                'url'       => 'director/config/files',
                'urlParams' => array(
                    'checksum'      => $this->view->config_checksum,
                    'deployment_id' => $deploymentId
                )
            ));
        }
    }
}
