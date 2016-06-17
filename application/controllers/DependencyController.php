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
	if ($this->service) {
            $this->getTabs()->add('service', array(
                'url'       => 'director/service',
                'urlParams' => array('name' => $this->service->object_name),
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
        if ($host = $this->params->get('host')) {
            $this->host = IcingaHost::load($host, $this->db());
        }

        if ($service = $this->params->get('service')) {
            $host_id = ($this->host ? $this->host->id : null);
            $this->service = IcingaService::load(array("host_id" => $host_id, "object_name" => $service), $this->db());
        }


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
        parent::init();

        if ($this->service) {
            $urlParams['name']= $this->service->object_name;
            if ($this->host) $urlParams['host'] = $this->host->object_name;
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

                if ($hostname = $this->params->get('host')) {
                    $this->view->host = IcingaHost::load($hostname, $db);
                    $params['child_host_id'] = $this->view->host->id;
                }

                if ($service = $this->params->get('service')) {
                    $svcKey['object_name']=$service;
                    $svcKey['host_id']=($this->view->host ? $this->view->host->id : null);

                    $this->view->service = IcingaService::load($svcKey, $db);
                    $params['child_service_id'] = $this->view->service->id;
                }

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


}
