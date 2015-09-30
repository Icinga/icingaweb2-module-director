<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;

class Director_DeploymentController extends ActionController
{
    public function showAction()
    {
        $this->view->deployment = DirectorDeploymentLog::load($this->params->get('id'), $this->db());
    }
}
