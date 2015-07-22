<?php

use Icinga\Module\Director\Web\Controller\ActionController;

class Director_ListController extends ActionController
{
    public function activitylogAction()
    {
        $this->setConfigTabs()->activate('activitylog');
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->loadTable('activityLog')->setConnection($this->db());
        $this->render('table');
    }

    public function datalistAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add list'),
            'director/datalist/add'
        );

        $this->setConfigTabs()->activate('datalist');
        $this->view->title = $this->translate('Data lists');
        $this->view->table = $this->loadTable('datalist')->setConnection($this->db());
        $this->render('table');
    }

    public function importsourceAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add import source'),
            'director/importsource/add'
        );

        $this->setImportTabs()->activate('importsource');
        $this->view->title = $this->translate('Import source');
        $this->view->table = $this->loadTable('importsource')->setConnection($this->db());
        $this->render('table');
    }

    public function importrunAction()
    {
        $this->setImportTabs()->activate('importrun');
        $this->view->title = $this->translate('Import runs');
        $this->view->table = $this->loadTable('importrun')->setConnection($this->db());
        $this->render('table');
    }

    public function datafieldAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add field'),
            'director/datafield/add'
        );

        $this->setConfigTabs()->activate('datafield');
        $this->view->title = $this->translate('Data fields');
        $this->view->table = $this->loadTable('datafield')->setConnection($this->db());
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
