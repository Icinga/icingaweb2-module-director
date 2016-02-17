<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class EndpointController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object && $this->object->hasApiUser()) {
            $params['endpoint'] = $this->object->object_name;

            $this->getTabs()->add('inspect', array(
                'url'       => 'director/inspect/types',
                'urlParams' => $params,
                'label'     => $this->translate('Inspect')
            ));
        }
    }
}
