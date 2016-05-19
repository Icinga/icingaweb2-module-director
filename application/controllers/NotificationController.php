<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaService;

class NotificationController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object && $this->object->object_type === 'apply') {
            $this->getTabs()->add('assign', array(
                'url'       => 'director/notification/assign',
                'urlParams' => $this->object->getUrlParams(),
                'label'     => 'Assign'
            ));

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

    public function assignAction()
    {
        $this->getTabs()->activate('assign');
        $this->view->form = $form = $this->loadForm('icingaNotificationAssignment');
        $form
            ->setIcingaObject($this->object)
            ->setDb($this->db());
        if ($id = $this->params->get('rule_id')) {
            $this->view->actionLinks = $this->view->qlink(
                $this->translate('back'),
                $this->getRequest()->getUrl()->without('rule_id'),
                null,
                array('class' => 'icon-left-big')
            );
            $form->loadObject($id);
        }
        $form->handleRequest();

        $this->view->table = $this->loadTable('icingaObjectAssignment')
            ->setObject($this->object);
        $this->view->title = 'Assign notification';
        $this->render('object/fields', null, true); // TODO: render table
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
