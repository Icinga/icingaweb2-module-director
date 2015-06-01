<?php

use Icinga\Module\Director\ActionController;

class Director_ListController extends ActionController
{
    public function hostsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Host'),
            'director/object/host'
        );
        $this->view->title = $this->translate('Icinga Hosts');
        $this->view->table = $this->loadTable('icingaHost')->setConnection($this->db());
        $this->render('table');
    }

    public function commandsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Command'),
            'director/object/command'
        );
        $this->view->title = $this->translate('Icinga Commands');
        $this->view->table = $this->loadTable('icingaCommand')->setConnection($this->db());
        $this->render('table');
    }

    public function zonesAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Zone'),
            'director/object/zone'
        );
        $this->view->title = $this->translate('Icinga Zones');
        $this->view->table = $this->loadTable('icingaZone')->setConnection($this->db());
        $this->render('table');
    }

    public function activitylogAction()
    {
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->loadTable('activityLog')->setConnection($this->db());
        $this->render('table');
    }
}
