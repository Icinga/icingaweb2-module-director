<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;

class EndpointController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->isPeer()) {
            $params['endpoint'] = $this->object->object_name;

            $this->getTabs()->add('inspect', array(
                'url'       => 'director/inspect/types',
                'urlParams' => $params,
                'label'     => $this->translate('Inspect')
            ));
        }
    }

    protected function isPeer()
    {
        if (! $this->object) return false;

        $apiconfig = $this->Config()->getSection('api');
        $host = $apiconfig->get('address');
        $port = $apiconfig->get('port');

        if ($host === $this->object->host
            && $port === $this->object->port
        ) {
            return true;
        }
    }
}
