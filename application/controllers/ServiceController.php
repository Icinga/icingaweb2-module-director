<?php

use Icinga\Module\Director\Web\Controller\ObjectController;

class Director_ServiceController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object) {
            $this->getTabs()->add('assign', array(
                'url' => 'director/service/assign',
                'urlParams' => array('name' => $this->object->object_name),
                'label' => 'Assign'
            ));
        }
    }

    public function assignAction()
    {
        $this->getTabs()->activate('assign');
        $this->view->form = $form = $this->loadForm('icingaAssignServiceToHost');
        $form
            ->setIcingaObject($this->object)
            ->setDb($this->db())
            ->handleRequest();
        $this->view->title = 'Assign service to host';
        $this->render('object/form', null, true);
    }
}
