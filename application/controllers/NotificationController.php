<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class NotificationController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/notifications');
    }

    // TODO: KILL IT
    public function init()
    {
        parent::init();
        // TODO: Check if this is still needed, remove it otherwise
        /** @var \Icinga\Web\Widget\Tab $tab */
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

    /**
     * @param DirectorObjectForm $form
     */
    protected function onObjectFormLoaded(DirectorObjectForm $form)
    {
        if (! $this->object) {
            return;
        }

        if ($this->object->isTemplate()) {
            $form->setListUrl('director/notifications/templates');
        } else {
            $form->setListUrl('director/notifications/applyrules');
        }
    }

    protected function hasBasketSupport()
    {
        return $this->object->isTemplate() || $this->object->isApplyRule();
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

        if (! $this->allowsObject($this->object)) {
            throw new NotFoundError('No such object available');
        }

        return $this->object;
    }
}
