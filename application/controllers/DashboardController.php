<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Dashboard\Dashboard;
use Icinga\Module\Director\Web\Controller\ActionController;

class DashboardController extends ActionController
{
    public function indexAction()
    {
        if ($this->getRequest()->isGet()) {
            $this->setAutorefreshInterval(10);
        }

        $this->view->title = $this->translate('Icinga Director');
        $this->singleTab($this->translate('Overview'));
        $dashboards = array();
        foreach (array('Objects', 'Deployment', 'Data') as $name) {
            $dashboard = Dashboard::loadByName($name, $this->db(), $this->view);
            if ($dashboard->isAvailable()) {
                $dashboards[$name] = $dashboard;
            }
        }

        $this->view->dashboards = $dashboards;
    }
}
