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
        $form = DirectorDatafieldForm::load()
            ->setDb($this->db());

        if ($id = $this->params->get('id')) {
            $form->loadObject((int) $id);
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
