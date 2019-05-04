<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class DependenciesController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        return $res;
    }
}
