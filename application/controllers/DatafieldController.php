<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\DirectorDatafieldForm;
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

        $form = DirectorDatafieldForm::load()
            ->setSuccessUrl('director/data/fields')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
            $this->addTitle(
                $this->translate('Modify %s'),
                $form->getObject()->varname
            );
            $this->addSingleTab($this->translate('Edit a Field'));
        } else {
            $this->addTitle($this->translate('Add a new Data Field'));
            $this->addSingleTab($this->translate('New Field'));
        }

        $form->handleRequest();
        $this->content()->add($form);
    }
}
