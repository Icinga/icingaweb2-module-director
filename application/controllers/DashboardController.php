<?php

namespace Icinga\Module\Director\Controllers;

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

        $this->setTitle($this->translate('Icinga Director'));
        $names = $this->params->getValues('name', array('Objects', 'Deployment', 'Data'));
        if (count($names) === 1) {
            // TODO: Find a better way for this
            $this->addSingleTab($this->translate(ucfirst($names[0])));
        } else {
            $this->addSingleTab($this->translate('Overview'));
        }

        $cntDashboards = 0;
        foreach ($names as $name) {
            $dashboard = Dashboard::loadByName($name, $this->db());
            if ($dashboard->isAvailable()) {
                $cntDashboards++;
                $this->content()->add($dashboard);
            }
        }

        if ($cntDashboards === 0) {
            $msg = $this->translate(
                'No dashboard available, you might have not enough permissions'
            );
            $this->content()->add($msg);
        }
    }
}
