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

        $mainDashboards = ['Objects', 'Alerts', 'Automation', 'Deployment', 'Data'];
        $this->setTitle($this->translate('Icinga Director - Main Dashboard'));
        $names = $this->params->getValues('name', $mainDashboards);
        if (count($names) === 1) {
            $name = $names[0];
            $dashboard = Dashboard::loadByName($name, $this->db());
            $this->tabs($dashboard->getTabs())->activate($name);
        } else {
            $this->addSingleTab($this->translate('Overview'));
        }

        $cntDashboards = 0;
        foreach ($names as $name) {
            if ($name instanceof Dashboard) {
                $dashboard = $name;
            } else {
                $dashboard = Dashboard::loadByName($name, $this->db());
            }
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
