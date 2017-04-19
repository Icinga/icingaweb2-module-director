<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ServicesController extends ObjectsController
{
    protected $multiEdit = array(
        'disabled'
    );

    public function init()
    {
        parent::init();
        $this->view->tabs->remove('objects');
    }

    public function indexAction()
    {
        $r = $this->getRequest();
        if ($r->getActionName() !== 'templates' && ! $this->getRequest()->isApiRequest()) {
            $this->redirectNow('director/services/templates');
        }

        return parent::indexAction();
    }
}
