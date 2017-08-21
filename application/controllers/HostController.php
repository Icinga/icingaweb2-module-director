<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Forms\IcingaAddServiceForm;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Forms\IcingaServiceSetForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\SelfService;
use Icinga\Module\Director\Web\Table\IcingaHostAppliedForServiceTable;
use Icinga\Module\Director\Web\Table\IcingaHostAppliedServicesTable;
use Icinga\Module\Director\Web\Table\IcingaHostServiceTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetServiceTable;
use Icinga\Web\Url;
use ipl\Html\Link;

class HostController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    protected function getHostgroupRestriction()
    {
        return new HostgroupRestriction($this->db(), $this->Auth());
    }

    public function editAction()
    {
        parent::editAction();
        $this->addOptionalMonitoringLink();
    }

    public function serviceAction()
    {
        $host = $this->getHostObject();
        $this->addServicesHeader();
        $this->addTitle($this->translate('Add Service: %s'), $host->getObjectName());
        $this->content()->add(
            IcingaAddServiceForm::load()
                ->setHost($host)
                ->setDb($this->db())
                ->handleRequest()
        );
    }

    public function servicesetAction()
    {
        $host = $this->getHostObject();
        $this->addServicesHeader();
        $this->addTitle($this->translate('Add Service Set: %s'), $host->getObjectName());
        $this->content()->add(
            IcingaServiceSetForm::load()
                ->setHost($host)
                ->setDb($this->db())
                ->handleRequest()
        );
    }

    protected function addServicesHeader()
    {
        $host = $this->getHostObject();
        $hostname = $host->getObjectName();
        $this->tabs()->activate('services');

        $this->actions()->add(Link::create(
            $this->translate('Add service'),
            'director/host/service',
            ['name' => $hostname],
            ['class' => 'icon-plus']
        ))->add(Link::create(
            $this->translate('Add service set'),
            'director/host/serviceset',
            ['name' => $hostname],
            ['class' => 'icon-plus']
        ));
    }

    public function servicesAction()
    {
        $this->addServicesHeader();
        $db = $this->db();
        $host = $this->getHostObject();
        $this->addTitle($this->translate('Services: %s'), $host->getObjectName());
        $content = $this->content();
        $table = IcingaHostServiceTable::load($host)
            ->setTitle($this->translate('Individual Service objects'));

        if (count($table)) {
            $content->add($table);
        }

        if ($applied = $host->vars()->get($db->settings()->magic_apply_for)) {
            if ($applied instanceof CustomVariableDictionary) {
                $table = IcingaHostAppliedForServiceTable::load($host, $applied)
                    ->setTitle($this->translate('Generated from host vars'));
                if (count($table)) {
                    $content->add($table);
                }
            }
        }

        /** @var IcingaHost[] $parents */
        $parents = IcingaTemplateRepository::instanceByObject($this->object)
            ->getTemplatesFor($this->object);
        foreach ($parents as $parent) {
            $table = IcingaHostServiceTable::load($parent)->setInheritedBy($host);
            if (count($table)) {
                $content->add(
                    $table->setTitle(sprintf(
                        'Inherited from %s',
                        $parent->getObjectName()
                    ))
                );
            }
        }

        $this->addHostServiceSetTables($host);
        foreach ($parents as $parent) {
            $this->addHostServiceSetTables($parent, $host);
        }

        $appliedSets = AppliedServiceSetLoader::fetchForHost($host);
        foreach ($appliedSets as $set) {
            $title = sprintf($this->translate('%s (Applied Service set)'), $set->getObjectName());

            $content->add(
                IcingaServiceSetServiceTable::load($set)
                    // ->setHost($host)
                    ->setAffectedHost($host)
                    ->setTitle($title)
            );
        }

        $table = IcingaHostAppliedServicesTable::load($host)
            ->setTitle($this->translate('Applied services'));

        if (count($table)) {
            $content->add($table);
        }
    }

    protected function addHostServiceSetTables(IcingaHost $host, IcingaHost $affectedHost = null)
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
            )->where('hs.host_id = ?', $host->get('id'));

        $sets = IcingaServiceSet::loadAll($db, $query, 'object_name');
        /** @var IcingaServiceSet $set*/
        foreach ($sets as $name => $set) {
            $title = sprintf($this->translate('%s (Service set)'), $name);
            $this->content()->add(
                IcingaServiceSetServiceTable::load($set)
                    ->setHost($host)
                    ->setAffectedHost($affectedHost)
                    ->setTitle($title)
            );
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
            'host_id'     => $host->get('id'),
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
            'host_id'     => $from->get('id')
        ], $this->db());

        // TODO: we want to eventually show the host template name, doesn't work
        //       as template resolution would break.
        // $parent->object_name = $from->object_name;

        $service = IcingaService::create([
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->get('id'),
            'imports'     => [$parent],
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ], $db);

        $this->addTitle($this->translate('Inherited service: %s'), $serviceName);

        $form = IcingaServiceForm::load()
            ->setDb($db)
            ->setHost($host)
            ->setInheritedFrom($from->getObjectName())
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
        )->where('ss.host_id = ?', $this->object->get('id'));

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
            'host_id'     => $host->get('id'),
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
            if ($host->isObject()
                && $mon->isAvailable()
                && $mon->hasHost($host->getObjectName())
            ) {
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
        /** @var IcingaHost $this->object */
        return $this->object;
    }
}
