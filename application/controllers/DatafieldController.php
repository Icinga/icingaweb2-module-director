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

        $form = $this->view->form = $this->loadForm('directorDatafield')
            ->setSuccessUrl('director/data/fields')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
            $this->view->title = sprintf(
                $this->translate('Modify %s'),
                $form->getObject()->varname
            );
            $this->singleTab($this->translate('Edit a field'));
        } else {
            $this->view->title = $this->translate('Add a new Data Field');
            $this->singleTab($this->translate('New field'));
        }

        $form->handleRequest();
        $this->render('object/form', null, true);
    }
}
