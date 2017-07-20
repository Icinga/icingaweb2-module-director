<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class EndpointController extends ObjectController
{
    public function init()
    {
        $this->assertPermission('director/inspect');
        parent::init();
        if ($this->object && $this->object->hasApiUser()) {
            $params['endpoint'] = $this->object->object_name;
        }
    }
}
