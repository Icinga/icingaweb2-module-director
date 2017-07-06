<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaHost;

class ServiceController extends ObjectController
{
    /** @var IcingaHost */
    protected $host;

    protected $set;

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

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    public function init()
    {
        if ($host = $this->params->get('host')) {
            $this->host = IcingaHost::load($host, $this->db());
        } elseif ($set = $this->params->get('set')) {
            $this->set = IcingaServiceSet::load(array('object_name' => $set), $this->db());
        } elseif ($apply = $this->params->get('apply')) {
            $this->apply = IcingaService::load(
                array('object_name' => $apply, 'object_type' => 'template'),
                $this->db()
            );
        }

        parent::init();

        if ($this->object) {
            if ($this->host) {
                foreach ($this->getTabs()->getTabs() as $tab) {
                    $tab->getUrl()->setParam('host', $this->host->object_name);
                }
            }

            if (! $this->set && $this->object->service_set_id) {
                $this->set = $this->object->getRelated('service_set');
            }
        }

        if ($this->host) {
            $this->getTabs()->add('services', array(
                'url'       => 'director/host/services',
                'urlParams' => array('name' => $this->host->object_name),
                'label'     => $this->translate('Services'),
            ));
        } elseif ($this->set) {
            $this->getTabs()->add('services', array(
                'url'       => 'director/serviceset/services',
                'urlParams' => array('name' => $this->set->object_name),
                'label'     => $this->translate('Services'),
            ));
        }
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

    /**
     * @param IcingaServiceForm $form
     */
    protected function beforeHandlingAddRequest($form)
    {
        if ($this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }

    public function editAction()
    {
        $this->tabs()->activate('modify');

        /** @var IcingaService $object */
        $object = $this->object;

        if ($this->host) {
            $this->view->actionLinks = $this->view->qlink(
                $this->translate('back'),
                'director/host/services',
                array('name' => $this->host->object_name),
                array('class' => 'icon-left-big')
            );
        }

        $form = $this
            ->loadForm('icingaService')
            ->setDb($this->db());

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
        $this->actions()->add($this->createCloneLink());

        $this->view->title = $object->object_name;
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
        // $this->setViewScript('object/form');
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
        if ($name === 'icingaService') {
            if ($this->host) {
                $form->setHost($this->host);
            } elseif ($this->set) {
                $form->setServiceSet($this->set)->setSuccessUrl(
                    'director/serviceset/services',
                    array('name' => $this->set->object_name)
                );
            }
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

                if ($this->set) {
                    $this->view->set = $this->set;
                    $params['service_set_id'] = $this->set->id;
                }
                $this->object = IcingaService::load($params, $db);
            } else {
                parent::loadObject();
            }
        }
        $this->view->undeployedChanges = $this->countUndeployedChanges();
        $this->view->totalUndeployedChanges = $this->db()
            ->countActivitiesSinceLastDeployedConfig();

        return $this->object;
    }
}
