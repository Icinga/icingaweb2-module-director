<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaService;

class NotificationController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/notifications');
    }

    public function init()
    {
        parent::init();
        // TODO: Check if this is still needed, remove it otherwise
        if ($this->object && $this->object->object_type === 'apply') {
            if ($host = $this->params->get('host')) {
                foreach ($this->getTabs()->getTabs() as $tab) {
                    $tab->getUrl()->setParam('host', $host);
                }
            }

            if ($service = $this->params->get('service')) {
                foreach ($this->getTabs()->getTabs() as $tab) {
                    $tab->getUrl()->setParam('service', $service);
                }
            }
        }
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                if ($hostname = $this->params->get('host')) {
                    $this->view->host = IcingaHost::load($hostname, $db);
                    $params['host_id'] = $this->view->host->id;
                }

                if ($service = $this->params->get('service')) {
                    $this->view->service = IcingaService::load($service, $db);
                    $params['service_id'] = $this->view->service->id;
                }

                $this->object = IcingaNotification::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
