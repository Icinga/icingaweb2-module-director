<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaDependency;
use Icinga\Module\Director\Objects\IcingaService;

class DependencyController extends ObjectController
{

    protected $host;
    protected $service;

    protected $apply;

    protected function beforeTabs()
    {
        if ($this->object) {
            if ($this->object->child_service_id) {
                $this->service = IcingaService::load(array("id" => $this->object->child_service_id), $this->db());
            }
            if ($this->object->child_host_id) {
                $this->host = IcingaHost::loadWithAutoIncId($this->object->child_host_id, $this->db());
            }
        } else {

            if ($host = $this->params->get('host')) {
                $this->host = IcingaHost::load($host, $this->db());
            }
            if ($service_id = $this->params->get('service_id')) {
                $this->service = IcingaService::load(array("id" => $service_id), $this->db());
            } else if ($service = $this->params->get('service')) {
                   $host_id = ($this->host ? $this->host->id : null);
                   $this->service = IcingaService::load(array("host_id" => $host_id, "object_name" => $service), $this->db());
            }
        }
 
        if ($this->service) { 
            $params=array();
            if ($this->service->object_type == "apply") {
                $params['id'] = $this->service->id;
            } else if ($this->service->object_type == "template") {
                $params['name']=$this->service->object_name;
            } else {
                $params['name']=$this->service->object_name;
                if ($this->host) {
                    $params['host']=$this->host->object_name;
                }
            }
            $this->getTabs()->add('service', array(
                'url'       => 'director/service',
                'urlParams' => $params,
                'label'     => $this->translate('Service'),
            ));
        } else {
            if ($this->host) {
                $this->getTabs()->add('host', array(
                    'url'       => 'director/host',
                    'urlParams' => array('name' => $this->host->object_name),
                    'label'     => $this->translate('Host'),
                ));
            }
        }
    }

    public function init()
    {
        parent::init();

        if ($apply = $this->params->get('apply')) {
            $this->apply = IcingaDependency::load(
                array('object_name' => $apply, 'object_type' => 'template'),
                $this->db()
            );
        }

        if ($this->service) {
            if ($this->service->object_type=="apply") {
                $urlParams['id']= $this->service->id;
            } else {
                $urlParams['name']= $this->service->object_name;
                if ($this->host) $urlParams['host'] = $this->host->object_name;
            }
            $this->getTabs()->add('service_dependencies', array(
                'url'       => 'director/service/dependencies',
                'urlParams' => $urlParams,
                'label'     => $this->translate('Service Dependencies'),
            )); 
        } else {
            if ($this->host) {
                $this->getTabs()->add('dependencies', array(
                    'url'       => 'director/host/dependencies',
                    'urlParams' => array('name' => $this->host->object_name),
                    'label'     => $this->translate('Dependencies'),
                ));
            }
        }
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                $this->object = IcingaDependency::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->service) {
            $this->view->title = $this->service->object_name . ': ' . $this->view->title;
        } else {
            if ($this->host) {
                $this->view->title = $this->host->object_name . ': ' . $this->view->title;
            }
        }

        if ($this->apply) {
            $this->view->title = sprintf(
                $this->translate('Apply "%s"'),
                $this->apply->object_name
            );
        }
    }

    public function loadForm($name)
    {
        $form = parent::loadForm($name);
        if ($name === 'icingaDependency' && $this->host) {
            $form->setHost($this->host);
        }
        if ($name === 'icingaDependency' && $this->service) {
            $form->setService($this->service);
        }

        return $form;
    }

    protected function beforeHandlingAddRequest($form)
    {
        if ($this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }



}
