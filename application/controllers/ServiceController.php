<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaHost;

class ServiceController extends ObjectController
{
    protected $host;

    protected $apply;

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

        if ($apply = $this->params->get('apply')) {
            $this->apply = IcingaService::load(
                array('object_name' => $apply, 'object_type' => 'template'),
                $this->db()
            );
        }

        parent::init();

        if ($this->object) {
            $tabs = $this->getTabs();
            if ($this->host) {
                foreach ($tabs->getTabs() as $tab) {
                    $tab->getUrl()->setParam('host', $this->host->object_name);
                }
            }
            if ($this->object->object_type == "apply") {
                $urlparams['id']=$this->object->id;
            } else {
                $urlparams['name']=$this->object->object_name;
            }
            if ($this->host) $urlparams['host']=$this->host->object_name;
            $tabs->add('service_dependencies', array(
                'url'       => 'director/service/dependencies',
                'urlParams' => $urlparams,
                'label'     => 'Service Dependencies'
            ));

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

        if ($this->apply) {
            $this->view->title = sprintf(
                $this->translate('Apply "%s"'),
                $this->apply->object_name
            );
        }
    }

    protected function beforeHandlingAddRequest($form)
    {
        if ($this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }

    public function editAction()
    {
        $object = $this->object;

        if ($this->host && $object->usesVarOverrides()) {

            $parent = IcingaService::create(array(
                'object_type' => 'template',
                'object_name' => 'myself',
                'vars'        => $object->vars,
            ), $this->db());

            $object->vars = $this->host->getOverriddenServiceVars($object->object_name);
            $object->imports()->add($parent);
        }

        parent::editAction();

        if ($this->host) {
            $this->view->subtitle = sprintf(
                $this->translate('(on %s)'),
                $this->host->object_name
            );
        }

        if ($object->isTemplate()
            && $object->getResolvedProperty('check_command_id')
        ) {

            $this->view->actionLinks .= ' ' . $this->view->qlink(
                'Create apply-rule',
                'director/service/add',
                array('apply' => $object->object_name),
                array('class'    => 'icon-plus')
            );

        }
    }

    public function assignAction()
    {
        $service = $this->object;
        $this->view->stayHere = true;

        $this->view->actionLinks = $this->view->qlink(
            $this->translate('back'),
            $this->getRequest()->getUrl()->without('rule_id'),
            null,
            array('class' => 'icon-left-big')
        );

        $this->getTabs()->activate('applied');
        $this->view->title = sprintf(
            $this->translate('Apply: %s'),
            $service->object_name
        );
        $this->view->table = $this->loadTable('IcingaAppliedService')
            ->setService($service)
            ->setConnection($this->db());

        $this->setViewScript('objects/table');
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

    public function dependenciesAction()
    {
        $service = $this->object;


        if ($this->object->object_type == "apply") {
            $urlparams['service_id']=$this->object->id;
        } else { 
            $urlparams['service']=$this->object->object_name;
        }
        if ($this->host) $urlparams['host']=$this->host->object_name;
 
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add dependency'),
            'director/dependency/add',
            $urlparams,
            array('class' => 'icon-plus')
        );

        $this->getTabs()->activate('service_dependencies');
        $this->view->title = sprintf(
            $this->translate('Dependencies: %s'),
            $service->object_name
        );

        $this->view->table = $this->loadTable('IcingaServiceDependency');
        if ($this->host) $this->view->table->setHost($this->host);
        if ($service) $this->view->table->setService($service);
        $this->view->table->enforceFilter('child_service_id', $service->id)
            ->setConnection($this->db());
    }


}
