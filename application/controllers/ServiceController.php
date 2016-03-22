<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaHost;

class ServiceController extends ObjectController
{
    protected $host;

    protected function beforeTabs()
    {
        if ($this->host) {
            $this->getTabs()->add('host', array(
                'url'       => 'director/host',
                'urlParams' => array('name' => $this->host->object_name),
                'label'     => $this->translate('Host'),
            ));
        }
    }

    public function init()
    {
        if ($host = $this->params->get('host')) {
            $this->host = IcingaHost::load($host, $this->db());
        }

        parent::init();

        if ($this->object && $this->object->object_type === 'apply') {
            $this->getTabs()->add('assign', array(
                'url'       => 'director/service/assign',
                'urlParams' => $this->object->getUrlParams(),
                'label'     => $this->translate('Assign')
            ));

            if ($this->host) {
                foreach ($this->getTabs()->getTabs() as $tab) {
                    $tab->getUrl()->setParam('host', $this->host->object_name);
                }
            }
        }

        if ($this->host) {
            $this->getTabs()->add('services', array(
                'url'       => 'director/host/services',
                'urlParams' => array('name' => $this->host->object_name),
                'label'     => $this->translate('Services'),
            ));
        }
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->host) {
            $this->view->title = $this->host->object_name . ': ' . $this->view->title;
        }
    }

    public function assignAction()
    {
        $this->getTabs()->activate('assign');
        $this->view->form = $form = $this->loadForm('icingaServiceAssignment');
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

        $this->view->title = 'Assign service to host';
        $this->render('object/fields', null, true); // TODO: render table
    }

    public function loadForm($name)
    {
        $form = parent::loadForm($name);
        if ($name === 'icingaService' && $this->host) {
            $form->setHost($this->host);
        }

        return $form;
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

                $this->object = IcingaService::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
