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

    public function hostgroupsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Hostgroup'),
            'director/object/hostgroup'
        );
        $this->view->title = $this->translate('Icinga Hostgroups');
        $this->view->table = $this->loadTable('icingaHostGroup')->setConnection($this->db());
        $this->render('table');
    }

    public function hostgroupmembersAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Hostgroup Member'),
            'director/object/hostgroupmember'
        );
        $this->view->title = $this->translate('Icinga Hostgroup Members');
        $this->view->table = $this->loadTable('icingaHostGroupMember')->setConnection($this->db());
        $this->render('table');
    }

    public function hostvarsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Host Variable'),
            'director/object/hostvar'
        );
        $this->view->title = $this->translate('Icinga Host Variables');
        $this->view->table = $this->loadTable('icingaHostVar')->setConnection($this->db());
        $this->render('table');
    }

    public function timeperiodsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Timeperiod'),
            'director/object/timeperiod'
        );
        $this->view->title = $this->translate('Icinga Timeperiods');
        $this->view->table = $this->loadTable('icingaTimePeriod')->setConnection($this->db());
        $this->render('table');
    }

    public function servicesAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Service'),
            'director/object/service'
        );
        $this->view->title = $this->translate('Icinga Services');
        $this->view->table = $this->loadTable('icingaService')->setConnection($this->db());
        $this->render('table');
    }

    public function servicegroupsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Servicegroup'),
            'director/object/servicegroup'
        );
        $this->view->title = $this->translate('Icinga Servicegroups');
        $this->view->table = $this->loadTable('icingaServiceGroup')->setConnection($this->db());
        $this->render('table');
    }

    public function servicegroupmembersAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Servicegroup Member'),
            'director/object/servicegroupmember'
        );
        $this->view->title = $this->translate('Icinga Servicegroup Members');
        $this->view->table = $this->loadTable('icingaServiceGroupMember')->setConnection($this->db());
        $this->render('table');
    }

    public function servicevarsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Service Variable'),
            'director/object/servicevar'
        );
        $this->view->title = $this->translate('Icinga Service Variables');
        $this->view->table = $this->loadTable('icingaServiceVar')->setConnection($this->db());
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

    public function commandargumentsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Command Argument'),
            'director/object/commandargument'
        );
        $this->view->title = $this->translate('Icinga Command Arguments');
        $this->view->table = $this->loadTable('icingaCommandArgument')->setConnection($this->db());
        $this->render('table');
    }

    public function usersAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add User'),
            'director/object/user'
        );
        $this->view->title = $this->translate('Icinga Users');
        $this->view->table = $this->loadTable('icingaUser')->setConnection($this->db());
        $this->render('table');
    }

    public function usergroupsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Usergroup'),
            'director/object/usergroup'
        );
        $this->view->title = $this->translate('Icinga Usergroups');
        $this->view->table = $this->loadTable('icingaUserGroup')->setConnection($this->db());
        $this->render('table');
    }

    public function usergroupmembersAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Usergroup Member'),
            'director/object/usergroupmember'
        );
        $this->view->title = $this->translate('Icinga Usergroup Members');
        $this->view->table = $this->loadTable('icingaUserGroupMember')->setConnection($this->db());
        $this->render('table');
    }

    public function endpointsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add Endpoint'),
            'director/object/endpoint'
        );
        $this->view->title = $this->translate('Icinga Endpoints');
        $this->view->table = $this->loadTable('icingaEndpoint')->setConnection($this->db());
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
