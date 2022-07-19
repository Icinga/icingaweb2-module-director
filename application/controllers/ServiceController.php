<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\Branch\UuidLookup;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\ObjectController;
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
        // Hint: having Host and Set loaded first is important for UUID lookups with legacy URLs
        $this->host = $this->getOptionalRelatedObjectFromParams('host', 'host');
        $this->set = $this->getOptionalRelatedObjectFromParams('service_set', 'set');
        parent::init();
        if ($this->object) {
            if ($this->host === null) {
                $this->host = $this->loadOptionalRelatedObject($this->object, 'host');
            }
            if ($this->set === null) {
                $this->set = $this->loadOptionalRelatedObject($this->object, 'service_set');
            }
        }
        $this->addOptionalHostTabs();
        $this->addOptionalSetTabs();
    }

    protected function getOptionalRelatedObjectFromParams($type, $parameter)
    {
        if ($id = $this->params->get("${parameter}_id")) {
            $key = (int) $id;
        } else {
            $key = $this->params->get($parameter);
        }
        if ($key !== null) {
            $table = DbObjectTypeRegistry::tableNameByType($type);
            $key = UuidLookup::findUuidForKey($key, $table, $this->db(), $this->getBranch());
            return $this->loadSpecificObject($table, $key);
        }

        return null;
    }

    protected function loadOptionalRelatedObject(IcingaObject $object, $relation)
    {
        $key = $object->getUnresolvedRelated($relation);
        if ($key === null) {
            if ($key = $object->get("${relation}_id")) {
                $key = (int) $key;
            } else {
                $key = $object->get($relation);
                // We reach this when accessing Service Template Fields
            }
        }

        if ($key === null) {
            return null;
        }

        $table = DbObjectTypeRegistry::tableNameByType($relation);
        $uuid = UuidLookup::findUuidForKey($key, $table, $this->db(), $this->getBranch());
        return $this->loadSpecificObject($table, $uuid);
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
            // TODO: use setTitle. And figure out, where we use this old route.
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
        if ($object->isTemplate() && $this->showNotInBranch($this->translate('Modifying Templates'))) {
            return;
        }

        $form = IcingaServiceForm::load()->setDb($this->db());
        $form->setBranch($this->getBranch());

        if ($this->host) {
            $this->actions()->add(Link::create(
                $this->translate('back'),
                'director/host/services',
                ['uuid' => $this->host->getUniqueId()->toString()],
                ['class' => 'icon-left-big']
            ));
            $form->setHost($this->host);
        }

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

    protected function getLegacyKey()
    {
        if ($key = $this->params->get('id')) {
            $key = (int) $key;
        } else {
            $key = $this->params->get('name');
        }

        if ($key === null) {
            throw new \InvalidArgumentException('uuid, name or id required');
        }

        return $key;
    }

    protected function loadObject()
    {
        if ($this->params->has('uuid')) {
            parent::loadObject();
            return;
        }

        $key = $this->getLegacyKey();
        // Hint: not passing 'object' as type, we still have name-based links in previews and similar
        $uuid = UuidLookup::findServiceUuid($this->db(), $this->getBranch(), null, $key, $this->host, $this->set);
        if ($uuid === null) {
            throw new NotFoundError('Not found');
        }
        $this->params->set('uuid', $uuid->toString());
        parent::loadObject();
    }

    protected function addOptionalHostTabs()
    {
        if ($this->host === null) {
            return;
        }
        $hostname = $this->host->getObjectName();
        $tabs = new Tabs();
        $urlParams = ['uuid' => $this->host->getUniqueId()->toString()];
        $tabs->add('host', [
            'url' => 'director/host',
            'urlParams' => $urlParams,
            'label' => $this->translate('Host'),
        ])->add('services', [
            'url' => 'director/host/services',
            'urlParams' => $urlParams,
            'label' => $this->translate('Services'),
        ]);

        $this->addParamToTabs('host', $hostname);
        $this->controls()->prependTabs($tabs);
    }

    protected function addOptionalSetTabs()
    {
        if ($this->set === null) {
            return;
        }
        $setName = $this->set->getObjectName();
        $tabs = new Tabs();
        $tabs->add('set', [
            'url' => 'director/serviceset',
            'urlParams' => ['name' => $setName],
            'label' => $this->translate('ServiceSet'),
        ])->add('services', [
            'url' => 'director/serviceset/services',
            'urlParams' => ['name' => $setName],
            'label' => $this->translate('Services'),
        ]);

        $this->addParamToTabs('serviceset', $setName);
        $this->controls()->prependTabs($tabs);
    }
}
