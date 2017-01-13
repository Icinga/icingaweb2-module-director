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
        if ($this->object) {
            $tabs = $this->getTabs();
            $tabs->add('services', array(
                'url'       => 'director/serviceset/services',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Services'
            ));
            $tabs->add('hosts', array(
                'url'       => 'director/serviceset/hosts',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Hosts'
            ));
        }
    }

    public function loadForm($name)
    {
        $form = parent::loadForm($name);
        if ($name === 'icingaServiceSet' && $this->host) {
            $form->setHost($this->host);
        }

        return $form;
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->host) {
            $this->view->title = sprintf(
                $this->translate('Add a service set to "%s"'),
                $this->host->object_name
            );
        }
    }

    public function servicesAction()
    {
        $db = $this->db();
        $set = $this->object;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add service'),
            'director/service/add',
            array('set' => $set->object_name),
            array('class' => 'icon-plus')
        );
        $this->view->stayHere = true;

        $this->getTabs()->activate('services');
        $this->view->title = sprintf(
            $this->translate('Services in this set: %s'),
            $set->object_name
        );

        $this->view->table = $this->loadTable('IcingaServiceSetService')
            ->setServiceSet($set)
            ->setConnection($db);

        $this->setViewScript('objects/table');
    }

    public function hostsAction()
    {
        $db = $this->db();
        $set = $this->object;
        $this->getTabs()->activate('hosts');
        $this->view->title = sprintf(
            $this->translate('Hosts using this set: %s'),
            $set->object_name
        );

        $this->view->table = $table = $this->loadTable('IcingaServiceSetHost')
            ->setServiceSet($set)
            ->setConnection($db);

        $this->setViewScript('objects/table');
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
