<?php

use Icinga\Module\Director\ActionController;

class Director_ListController extends ActionController
{
    public function activitylogAction()
    {
        $this->setConfigTabs()->activate('activitylog');
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->loadTable('activityLog')->setConnection($this->db());
        $this->render('table');
    }

    public function generatedconfigAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Generate'),
            'director/config/store'
        );

        $this->setConfigTabs()->activate('generatedconfig');
        $this->view->title = $this->translate('Generated Configs');
        $this->view->table = $this->loadTable('generatedConfig')->setConnection($this->db());
        $this->render('table');
    }
}
