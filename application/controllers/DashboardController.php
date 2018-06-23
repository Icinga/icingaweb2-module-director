<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Dashboard\Dashboard;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Form\DbSelectorForm;

class DashboardController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        // No special permissions required, override parent method
    }

    protected function addDbSelection()
    {
        if ($this->isMultiDbSetup()) {
            $form = new DbSelectorForm($this->Window(), $this->listAllowedDbResourceNames());
            $this->content()->add($form);
            $form->handleRequest($this->getRequest());
        }
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    public function indexAction()
    {
        if ($this->getRequest()->isGet()) {
            $this->setAutorefreshInterval(10);
        }

        $mainDashboards = ['Objects', 'Alerts', 'Automation', 'Deployment', 'Data'];
        $this->setTitle($this->translate('Icinga Director - Main Dashboard'));
        $names = $this->params->getValues('name', $mainDashboards);
        if (! $this->params->has('name')) {
            $this->addDbSelection();
        }
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
