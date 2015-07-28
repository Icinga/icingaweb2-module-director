<?php

use Icinga\Module\Director\Web\Controller\ActionController;

class Director_FieldController extends ActionController
{

    protected function tabs() {
        return $this->getTabs()->add('host', array(
            'url'       => 'director/field/host',
            'label'     => 'Host',
        ))->add('service', array(
            'url'       => 'director/field/service',
            'label'     => 'Service',
        ));
    }

    public function hostAction()
    {
        $this->tabs()->activate('host');

        $form = $this->view->form = $this->loadForm('icingaHostField')
            ->setSuccessUrl('director/field/host')
            ->setDb($this->db());

        $form->handleRequest();

        $this->render('object/form', null, true);
    }

    public function serviceAction()
    {
        $this->tabs()->activate('service');

        $form = $this->view->form = $this->loadForm('icingaServiceField')
            ->setSuccessUrl('director/field/service')
            ->setDb($this->db());

        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
