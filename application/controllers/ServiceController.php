<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Table\IcingaAppliedServiceTable;
use Icinga\Web\Widget\Tab;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\Tabs;

class ServiceController extends ObjectController
{
    /** @var IcingaHost */
    protected $host;

    protected $set;

    protected $apply;

    protected function checkDirectorPermissions()
    {
        if ($this->hasPermission('director/monitoring/services')) {
            $monitoring = new Monitoring();
            if ($monitoring->authCanEditService($this->Auth(), $this->getParam('host'), $this->getParam('name'))) {
                return;
            }
        }
        $this->assertPermission('director/hosts');
    }

    public function init()
    {
        if ($host = $this->params->get('host')) {
            $this->host = IcingaHost::load($host, $this->db());
        } elseif ($hostId = $this->params->get('host_id')) {
            $this->host = IcingaHost::loadWithAutoIncId($hostId, $this->db());
        } elseif ($set = $this->params->get('set')) {
            $this->set = IcingaServiceSet::load(['object_name' => $set], $this->db());
        } elseif ($apply = $this->params->get('apply')) {
            $this->apply = IcingaService::load(
                ['object_name' => $apply, 'object_type' => 'template'],
                $this->db()
            );
        }
        parent::init();

        if ($this->host) {
            $hostname = $this->host->getObjectName();
            $tabs = new Tabs();
            $tabs->add('host', [
                'url' => 'director/host',
                'urlParams' => ['name' => $hostname],
                'label' => $this->translate('Host'),
            ])->add('services', [
                'url'       => 'director/host/services',
                'urlParams' => ['name' => $hostname],
                'label'     => $this->translate('Services'),
            ]);

            $this->addParamToTabs('host', $hostname);
            $this->controls()->prependTabs($tabs);
        }

        if ($this->object) {
            if (! $this->set && $this->object->get('service_set_id')) {
                $this->set = $this->object->getRelated('service_set');
            }
        }

        if ($this->set) {
            $setName = $this->set->getObjectName();
            $tabs = new Tabs();
            $tabs->add('set', [
                'url'       => 'director/serviceset',
                'urlParams' => ['name' => $setName],
                'label'     => $this->translate('ServiceSet'),
            ])->add('services', [
                'url'       => 'director/serviceset/services',
                'urlParams' => ['name' => $setName],
                'label'     => $this->translate('Services'),
            ]);

            $this->addParamToTabs('serviceset', $setName);
            $this->controls()->prependTabs($tabs);
        }
    }

    protected function addParamToTabs($name, $value)
    {
        foreach ($this->tabs()->getTabs() as $tab) {
            /** @var Tab $tab */
            $tab->getUrl()->setParam($name, $value);
        }

        return $this;
    }

    public function addAction()
    {
        parent::addAction();
        if ($this->host) {
            $this->view->title = $this->host->object_name . ': ' . $this->view->title;
        } elseif ($this->set) {
            $this->view->title = sprintf(
                $this->translate('Add a service to "%s"'),
                $this->set->object_name
            );
        } elseif ($this->apply) {
            $this->view->title = sprintf(
                $this->translate('Apply "%s"'),
                $this->apply->object_name
            );
        }
    }

    protected function onObjectFormLoaded(DirectorObjectForm $form)
    {
        if ($this->set) {
            /** @var IcingaServiceForm$form */
            $form->setServiceSet($this->set);
        }
        if ($this->object === null && $this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }

    public function editAction()
    {
        $this->tabs()->activate('modify');

        /** @var IcingaService $object */
        $object = $this->object;
        $this->addTitle($object->getObjectName());

        if ($this->host) {
            $this->actions()->add(Link::create(
                $this->translate('back'),
                'director/host/services',
                ['name' => $this->host->getObjectName()],
                ['class' => 'icon-left-big']
            ));
        }

        $form = IcingaServiceForm::load()->setDb($this->db());

        if ($this->set) {
            $form->setServiceSet($this->set);
        }
        if ($this->host && $object->usesVarOverrides()) {
            $fake = IcingaService::create(array(
                'object_type' => 'object',
                'host_id' => $object->get('host_id'),
                'imports' => $object,
                'object_name' => $object->object_name,
                'use_var_overrides' => 'y',
                'vars' => $this->host->getOverriddenServiceVars($object->object_name),
            ), $this->db());

            $form->setObject($fake);
        } else {
            $form->setObject($object);
        }

        $form->handleRequest();
        $this->addActionClone();

        if ($this->host) {
            $this->view->subtitle = sprintf(
                $this->translate('(on %s)'),
                $this->host->object_name
            );
        }

        try {
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
        } catch (Exception $e) {
            // ignore the error, show no apply link
        }

        $this->content()->add($form);
    }

    public function assignAction()
    {
        // TODO: figure out whether and where we link to this
        /** @var IcingaService $service */
        $service = $this->object;
        $this->actions()->add(new Link(
            $this->translate('back'),
            $this->getRequest()->getUrl()->without('rule_id'),
            null,
            array('class' => 'icon-left-big')
        ));

        $this->tabs()->activate('applied');
        $this->addTitle(
            $this->translate('Apply: %s'),
            $service->getObjectName()
        );
        $table = (new IcingaAppliedServiceTable($this->db()))
            ->setService($service);
        $table->getAttributes()->set('data-base-target', '_self');

        $this->content()->add($table);
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($id = $this->params->get('id')) {
                $this->object = IcingaService::loadWithAutoIncId(
                    (int) $id,
                    $this->db()
                );
            } elseif ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                if ($this->host) {
                    // $this->view->host = $this->host;
                    $params['host_id'] = $this->host->id;
                }

                if ($this->set) {
                    // $this->view->set = $this->set;
                    $params['service_set_id'] = $this->set->id;
                }
                $this->object = IcingaService::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }
}
