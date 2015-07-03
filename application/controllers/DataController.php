<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Forms\DirectorDatafieldForm;

class Director_DataController extends ActionController
{
    public function addfieldAction()
    {
        $form = new DirectorDatafieldForm();

        $this->view->form = $form;
    }
}
