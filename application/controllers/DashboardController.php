<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Dashboard\Dashboard;
use Icinga\Module\Director\Web\Controller\ActionController;

class DashboardController extends ActionController
{
    protected function checkDirectorPermissions()
    {
    }

    public function indexAction()
    {
        if ($this->getRequest()->isGet()) {
            $this->setAutorefreshInterval(10);
        }

        $this->view->title = $this->translate('Icinga Director');
        $names = $this->params->getValues('name', array('Objects', 'Deployment', 'Data'));
        if (count($names) === 1) {
            // TODO: Find a better way for this
            $this->singleTab($this->translate(ucfirst($names[0])));
        } else {
            $this->singleTab($this->translate('Overview'));
        }
        $dashboards = array();
        foreach ($names as $name) {
            $dashboard = Dashboard::loadByName($name, $this->db(), $this->view);
            if ($dashboard->isAvailable()) {
                $dashboards[$name] = $dashboard;
            }
        }

        $this->view->dashboards = $dashboards;
    }
}
