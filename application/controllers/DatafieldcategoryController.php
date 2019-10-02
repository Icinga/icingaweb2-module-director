<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\DirectorDatafieldCategoryForm;
use Icinga\Module\Director\Web\Controller\ActionController;

class DatafieldcategoryController extends ActionController
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

        if ($name = $this->params->get('name')) {
            $edit = true;
        }

        $form = DirectorDatafieldCategoryForm::load()
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($name);
            $this->addTitle(
                $this->translate('Modify %s'),
                $form->getObject()->category_name
            );
            $this->addSingleTab($this->translate('Edit a Category'));
        } else {
            $this->addTitle($this->translate('Add a new Data Field Category'));
            $this->addSingleTab($this->translate('New Category'));
        }

        $form->handleRequest();
        $this->content()->add($form);
    }
}
