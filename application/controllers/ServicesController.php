<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ServicesController extends ObjectsController
{
    public function indexAction()
    {
        $r = $this->getRequest();
        if ($r->getActionName() !== 'templates' && ! $this->getRequest()->isApiRequest()) {
            $this->redirectNow('director/services/templates');
        }

        return parent::indexAction();
    }
}
