<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Controller\ObjectController;

class ServicesetController extends ObjectController
{
    protected $host;

    public function init()
    {
        if ($host = $this->params->get('host')) { 
            $this->host = IcingaHost::load($host, $this->db());
        }

        parent::init();
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                if ($this->host) {
                    $this->view->host = $this->host;
                    $params['host_id'] = $this->host->id;
                }

                $this->object = IcingaServiceSet::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
