<?php

namespace Icinga\Module\Director\Controllers;

use Exception;

class KickstartController extends DashboardController
{
    public function indexAction()
    {
        $this->singleTab($this->view->title = $this->translate('Kickstart'));
        $form = $this->view->form = $this->loadForm('kickstart');
        try {
            $form->setEndpoint($this->db()->getDeploymentEndpoint());
        } catch (Exception $e) {
            // Silently ignore DB errors
        }

        $form->handleRequest();
    }
}
