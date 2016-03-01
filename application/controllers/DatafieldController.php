<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class DatafieldController extends ActionController
{
    public function addAction()
    {
        $this->indexAction();
    }

    public function editAction()
    {
        $this->indexAction();
    }

    public function indexAction()
    {
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        if ($edit) {
            $this->view->title = $this->translate('Edit field');
            $this->getTabs()->add('editfield', array(
                'url'       => 'director/datafield/edit' . '?id=' . $id,
                'label'     => $this->view->title,
            ))->activate('editfield');
        } else {
            $this->view->title = $this->translate('Add field');
            $this->getTabs()->add('addfield', array(
                'url'       => 'director/datafield/add',
                'label'     => $this->view->title,
            ))->activate('addfield');
        }

        $form = $this->view->form = $this->loadForm('directorDatafield')
            ->setSuccessUrl('director/data/fields')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
    }
}
