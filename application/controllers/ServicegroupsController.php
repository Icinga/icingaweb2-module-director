<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class ServicegroupsController extends ObjectsController
{
    public function init()
    {
        parent::init();
        $this->view->tabs->remove('objects');
    }
}
