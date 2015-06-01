<?php

use Icinga\Module\Director\ActionController;

class Director_ObjectController extends ActionController
{
    public function hostAction()
    {
        $this->view->form = $this->loadForm('icingaHost')
            ->setDb($this->db())
            ->setSuccessUrl('director/list/hosts');

        if ($id = $this->params->get('id')) {
            $this->view->form->loadObject($id);
            $this->view->title = $this->translate('Modify Icinga Host');
        } else {
            $this->view->title = $this->translate('Add new Icinga Host');
        }
        $this->view->form->handleRequest();
        $this->render('form');
    }

    public function commandAction()
    {
        $this->view->form = $this->loadForm('icingaCommand')
            ->setDb($this->db())
            ->setSuccessUrl('director/list/commands');

        if ($id = $this->params->get('id')) {
            $this->view->form->loadObject($id);
            $this->view->title = $this->translate('Modify Icinga Command');
        } else {
            $this->view->title = $this->translate('Add new Icinga Command');
        }
        $this->view->form->handleRequest();
        $this->render('form');
    }

    public function commandargumentAction()
    {
        $this->view->form = $this->loadForm('icingaCommandArgument')
            ->setDb($this->db())
            ->setSuccessUrl('director/list/commandargument');

        if ($id = $this->params->get('id')) {
            $this->view->form->loadObject($id);
            $this->view->title = $this->translate('Modify Icinga Command Argument');
        } else {
            $this->view->title = $this->translate('Add new Icinga Command Argument');
        }
        $this->view->form->handleRequest();
        $this->render('form');
    }

    public function userAction()
    {
        $this->view->form = $this->loadForm('icingaUser')
            ->setDb($this->db())
            ->setSuccessUrl('director/list/users');

        if ($id = $this->params->get('id')) {
            $this->view->form->loadObject($id);
            $this->view->title = $this->translate('Modify Icinga User');
        } else {
            $this->view->title = $this->translate('Add new Icinga User');
        }
        $this->view->form->handleRequest();
        $this->render('form');
    }

    public function zoneAction()
    {
        $this->view->form = $this->loadForm('icingaZone')
            ->setDb($this->db())
            ->setSuccessUrl('director/list/zones');

        if ($id = $this->params->get('id')) {
            $this->view->title = $this->translate('Modify Icinga Zone');
            $this->view->form->loadObject($id);
        } else {
            $this->view->title = $this->translate('Add new Icinga Zone');
        }
        $this->view->form->handleRequest();
        $this->render('form');
    }
}
