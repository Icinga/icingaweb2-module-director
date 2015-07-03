<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Forms\DirectorDatafieldForm;

class Director_DataController extends ActionController
{
    public function addfieldAction()
    {
        $title = $this->translate('Add field');
        $this->getTabs()->add('addfield', array(
            'url'       => 'director/data/addfield',
            'label'     => $title,
        ))->activate('addfield');

        $form = new DirectorDatafieldForm();

        $this->view->title = $title;
        $this->view->form = $form;
    }
}
