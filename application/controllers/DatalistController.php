<?php

use Icinga\Module\Director\Web\Controller\ActionController;

class Director_DatalistController extends ActionController
{
    public function indexAction()
    {
        $this->view->title = $this->translate('Add list');
        $this->getTabs()->add('addlist', array(
            'url'       => 'director/data/addlist',
            'label'     => $this->view->title,
        ))->activate('addlist');

        $form = $this->view->form = $this->loadForm('directorDatalist')
            ->setSuccessUrl('director/list/datalist')
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $form->loadObject($id);
        }
        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
