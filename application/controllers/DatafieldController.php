<?php

use Icinga\Module\Director\Web\Controller\ActionController;

class Director_DatafieldController extends ActionController
{
    public function indexAction()
    {
        $this->view->title = $this->translate('Add field');
        $this->getTabs()->add('addfield', array(
            'url'       => 'director/data/addfield',
            'label'     => $this->view->title,
        ))->activate('addfield');

        $form = $this->view->form = $this->loadForm('directorDatafield')
            ->setSuccessUrl('director/list/datafield')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $form->loadObject($id);
        }
        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
