<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\Migrations;

class KickstartController extends DashboardController
{
    public function indexAction()
    {
        $this->singleTab($this->view->title = $this->translate('Kickstart'));
        $this->view->form = $this
            ->loadForm('kickstart')
            ->setEndpoint($this->db()->getDeploymentEndpoint())
            ->handleRequest();
    }
}
