<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class CommandsController extends ObjectsController
{
    public function indexAction()
    {
        protected $multiEdit = array(
          'imports',
          'timeout',
          'is_string',
          'disabled'
        );

        parent::indexAction();
        $validTypes = ['object', 'external_object'];
        $type = $this->params->get('type', 'object');
        if (! in_array($type, $validTypes)) {
            $type = 'object';
        }

        $this->table->setType($type);
    }
}
