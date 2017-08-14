<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\SelfService;
use Icinga\Web\Url;
use ipl\Html\Link;

class HostController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    protected function loadRestrictions()
    {
        return [$this->getHostgroupRestriction()];
    }

    protected function getHostgroupRestriction()
    {
        return new HostgroupRestriction($this->db(), $this->Auth());
    }

    /**
     * @param IcingaHostForm $form
     */
    protected function beforeHandlingAddRequest($form)
    {
        $form->setApi($this->getApiIfAvailable());
    }

    /**
     * @param IcingaHostForm $form
     */
    protected function beforeHandlingEditRequest($form)
    {
        $form->setApi($this->getApiIfAvailable());
    }

    public function editAction()
    {
        parent::editAction();
        $host = $this->object;
        try {
            $mon = $this->monitoring();
            if ($host->isObject() && $mon->isAvailable() && $mon->hasHost($host->object_name)) {
                $this->actions()->add(Link::create(
                    $this->translate('Show'),
                    'monitoring/host/show',
                    ['host' => $host->getObjectName()],
                    [
                        'class'            => 'icon-globe critical',
                        'data-base-target' => '_next'
                    ]
                ));
            }
        } catch (Exception $e) {
            // Silently ignore errors in the monitoring module
        }
    }

    public function servicesAction()
    {
        $db = $this->db();
        $host = $this->getHostObject();
        $hostname = $host->getObjectName();

        $this->tabs()->activate('services');
        $this->addTitle($this->translate('Services: %s'), $host->getObjectName());

        $this->actions()->add(Link::create(
            $this->translate('Add service'),
            'director/service/add',
            ['host' => $hostname],
            ['class' => 'icon-plus']
        ))->add(Link::create(
            $this->translate('Add service set'),
            'director/serviceset/add',
            ['host' => $hostname],
            ['class' => 'icon-plus']
        ));

        $resolver = $this->object->templateResolver();

        $tables = array();
        $table = $this->loadTable('IcingaHostService')
            ->setHost($host)
            ->setTitle($this->translate('Individual Service objects'))
            ->enforceFilter('host_id', $host->id)
            ->setConnection($db);

        if (count($table)) {
            $tables[0] = $table;
        }

        if ($applied = $host->vars()->get($db->settings()->magic_apply_for)) {
            $table = $this->loadTable('IcingaHostAppliedForService')
                ->setHost($host)
                ->setDictionary($applied)
                ->setTitle($this->translate('Generated from host vars'));

            if (count($table)) {
                $tables[1] = $table;
            }
        }

        $parents = $resolver->fetchResolvedParents();
        foreach ($parents as $parent) {
            $table = $this->loadTable('IcingaHostService')
                ->setHost($parent)
                ->setInheritedBy($host)
                ->enforceFilter('host_id', $parent->id)
                ->setConnection($db);
            if (! count($table)) {
                continue;
            }

            // dup dup
            $title = sprintf(
                'Inherited from %s',
                $parent->object_name
            );

            $tables[$title] = $table->setTitle($title);
        }

        $this->addHostServiceSetTables($host, $tables);
        foreach ($parents as $parent) {
            $this->addHostServiceSetTables($parent, $tables, $host);
        }

        $appliedSets = AppliedServiceSetLoader::fetchForHost($host);
        foreach ($appliedSets as $set) {
            $title = sprintf($this->translate('%s (Applied Service set)'), $set->getObjectName());
            $table = $this->loadTable('IcingaServiceSetService')
                ->setServiceSet($set)
                // ->setHost($host)
                ->setAffectedHost($host)
                ->setTitle($title)
                ->setConnection($db);

            $tables[$title] = $table;
        }

        $title = $this->translate('Applied services');
        $table = $this->loadTable('IcingaHostAppliedServices')
            ->setHost($host)
            ->setTitle($title)
            ->setConnection($db);

        if (count($table)) {
            $tables[$title] = $table;
        }

        foreach ($tables as $table) {
            $this->content()->add($table);
        }
    }

    protected function addHostServiceSetTables(IcingaHost $host, & $tables, IcingaHost $affectedHost = null)
    {
        $db = $this->db();
        if ($affectedHost === null) {
            $affectedHost = $host;
        }

        $query = $db->getDbAdapter()->select()
            ->from(
                array('ss' => 'icinga_service_set'),
                'ss.*'
            )->join(
                array('hsi' => 'icinga_service_set_inheritance'),
                'hsi.parent_service_set_id = ss.id',
                array()
            )->join(
                array('hs' => 'icinga_service_set'),
                'hs.id = hsi.service_set_id',
                array()
            )->where('hs.host_id = ?', $host->id);

        $sets = IcingaServiceSet::loadAll($db, $query, 'object_name');
        foreach ($sets as $name => $set) {
            $title = sprintf($this->translate('%s (Service set)'), $name);
            $table = $this->loadTable('IcingaServiceSetService')
                ->setServiceSet($set)
                ->setHost($host)
                ->setAffectedHost($affectedHost)
                ->setTitle($title)
                ->setConnection($db);

            $tables[$title] = $table;
        }
    }

    public function appliedserviceAction()
    {
        $db = $this->db();
        $host = $this->getHostObject();
        $serviceId = $this->params->get('service_id');
        $parent = IcingaService::loadWithAutoIncId($serviceId, $db);
        $serviceName = $parent->getObjectName();

        $service = IcingaService::create([
            'imports'     => $parent,
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ], $db);

        $this->addTitle(
            $this->translate('Applied service: %s'),
            $serviceName
        );

        $this->content()->add(
            IcingaServiceForm::load()
                ->setDb($db)
                ->setHost($host)
                ->setApplyGenerated($parent)
                ->setObject($service)
                ->handleRequest()
        );

        $this->commonForServices();
    }

    public function inheritedserviceAction()
    {
        $db = $this->db();
        $host = $this->getHostObject();
        $serviceName = $this->params->get('service');
        $from = IcingaHost::load($this->params->get('inheritedFrom'), $this->db());

        $parent = IcingaService::load([
            'object_name' => $serviceName,
            'host_id'     => $from->id
        ], $this->db());

        // TODO: we want to eventually show the host template name, doesn't work
        //       as template resolution would break.
        // $parent->object_name = $from->object_name;

        $service = IcingaService::create([
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'imports'     => [$parent],
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ], $db);

        $this->addTitle($this->translate('Inherited service: %s'), $serviceName);

        $form = IcingaServiceForm::load()
            ->setDb($db)
            ->setHost($host)
            ->setInheritedFrom($from->object_name)
            ->setObject($service)
            ->handleRequest();
        $this->content()->add($form);
        $this->commonForServices();
    }

    public function removesetAction()
    {
        // TODO: clean this up, use POST
        $db = $this->db()->getDbAdapter();
        $query = $db->select()->from(
            array('ss' => 'icinga_service_set'),
            array('id' => 'ss.id')
        )->join(
            array('si' => 'icinga_service_set_inheritance'),
            'si.service_set_id = ss.id',
            array()
        )->where(
            'si.parent_service_set_id = ?',
            $this->params->get('setId')
        )->where('ss.host_id = ?', $this->object->id);

        IcingaServiceSet::loadWithAutoIncId($db->fetchOne($query), $this->db())->delete();
        $this->redirectNow(
            Url::fromPath('director/host/services', array(
                'name' => $this->object->getObjectName()
            ))
        );
    }

    public function servicesetserviceAction()
    {
        $db = $this->db();
        $host = $this->getHostObject();
        $serviceName = $this->params->get('service');
        $set = IcingaServiceSet::load($this->params->get('set'), $db);

        $service = IcingaService::load([
            'object_name'    => $serviceName,
            'service_set_id' => $set->get('id')
        ], $this->db());
        $service = IcingaService::create([
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'imports'     => [$service],
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ], $db);

        // $set->copyVarsToService($service);
        $this->addTitle(
            $this->translate('%s on %s (from set: %s)'),
            $serviceName,
            $host->getObjectName(),
            $set->getObjectName()
        );

        $form = IcingaServiceForm::load()
            ->setDb($db)
            ->setHost($host)
            ->setServiceSet($set)
            ->setObject($service)
            ->handleRequest();
        $this->tabs()->activate('services');
        $this->content()->add($form);
        $this->commonForServices();
    }

    protected function commonForServices()
    {
        $host = $this->object;
        $this->actions()->add(Link::create(
            $this->translate('back'),
            'director/host/services',
            ['name' => $host->getObjectName()],
            ['class' => 'icon-left-big']
        ));
        $this->tabs()->activate('services');
    }

    public function agentAction()
    {
        $this->content()->add(
            IcingaHostAgentForm::load()
                ->setObject($this->requireObject())
                ->handleRequest()
        );
        $selfService = new SelfService($this->getHostObject(), $this->api());
        if ($os = $this->params->get('download')) {
            $selfService->handleLegacyAgentDownloads($os);
            return;
        }

        $selfService->renderTo($this);
        $this->tabs()->activate('agent');
    }

    protected function addOptionalMonitoringLink()
    {
        $host = $this->object;
        try {
            $mon = $this->monitoring();
            if ($host->isObject() && $mon->isAvailable() && $mon->hasHost($host->object_name)) {
                $this->actions()->add(Link::create(
                    $this->translate('Show'),
                    'monitoring/host/show',
                    ['host' => $host->getObjectName()],
                    [
                        'class'            => 'icon-globe critical',
                        'data-base-target' => '_next'
                    ]
                ));
            }
        } catch (Exception $e) {
            // Silently ignore errors in the monitoring module
        }
    }

    /**
     * @return IcingaHost
     */
    protected function getHostObject()
    {
        return $this->object;
    }
}
