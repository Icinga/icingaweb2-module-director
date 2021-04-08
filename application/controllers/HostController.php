<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Module\Director\Monitoring;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\Tabs;
use Exception;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\DirectorObject\Lookup\ServiceFinder;
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

class HostController extends ObjectController
{
    protected function checkDirectorPermissions()
    {
        if ($this->isServiceAction() && (new Monitoring())->authCanEditService(
            $this->Auth(),
            $this->getParam('name'),
            $this->getParam('service')
        )) {
            return;
        }

        if ($this->isServicesReadOnlyAction()) {
            $this->assertPermission('director/monitoring/services-ro');
            return;
        }

        if ($this->hasPermission('director/hosts')) { // faster
            return;
        }

        if ($this->canModifyHostViaMonitoringPermissions($this->getParam('name'))) {
            return;
        }

        $this->assertPermission('director/hosts'); // complain about default hosts permission
    }

    protected function isServicesReadOnlyAction()
    {
        return in_array($this->getRequest()->getActionName(), [
            'servicesro',
            'findservice',
            'invalidservice',
        ]);
    }

    protected function isServiceAction()
    {
        return in_array($this->getRequest()->getActionName(), [
            'servicesro',
            'findservice',
            'invalidservice',
            'servicesetservice',
            'appliedservice',
            'inheritedservice',
        ]);
    }

    protected function canModifyHostViaMonitoringPermissions($hostname)
    {
        if ($this->hasPermission('director/monitoring/hosts')) {
            $monitoring = new Monitoring();
            return $monitoring->authCanEditHost($this->Auth(), $hostname);
        }

        return false;
    }

    /**
     * @return HostgroupRestriction
     */
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
        $this->addTitle($this->translate('Add Service to %s'), $host->getObjectName());
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
        $this->addTitle($this->translate('Add Service Set to %s'), $host->getObjectName());
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

    public function findserviceAction()
    {
        $host = $this->getHostObject();
        $this->redirectNow(
            (new ServiceFinder($host, $this->getAuth()))
                ->getRedirectionUrl($this->params->get('service'))
        );
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function invalidserviceAction()
    {
        if (! $this->showInfoForNonDirectorService()) {
            $this->content()->add(Hint::error(sprintf(
                $this->translate('No such service: %s'),
                $this->params->get('service')
            )));
        }

        $this->servicesAction();
    }

    protected function showInfoForNonDirectorService()
    {
        try {
            $api = $this->getApiIfAvailable();
            if ($api) {
                $name = $this->params->get('name') . '!' . $this->params->get('service');
                $info = $api->getObject($name, 'Services');
                if (isset($info->attrs->source_location)) {
                    $source = $info->attrs->source_location;
                    $this->content()->add(Hint::info(Html::sprintf(
                        'The configuration for this object has not been rendered by'
                        . ' Icinga Director. You can find it on line %s in %s.',
                        Html::tag('strong', null, $source->first_line),
                        Html::tag('strong', null, $source->path)
                    )));
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

        /** @var IcingaHost[] $parents */
        $parents = IcingaTemplateRepository::instanceByObject($this->object)
            ->getTemplatesFor($this->object, true);
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

    /**
     * Hint: this duplicates quite some logic from servicesAction. We might want
     *       to clean this up, but as soon as we store fully resolved Services this
     *       will be obsolete anyways
     *
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Security\SecurityException
     * @throws \Icinga\Exception\MissingParameterException
     */
    public function servicesroAction()
    {
        $this->assertPermission('director/monitoring/services-ro');
        $host = $this->getHostObject();
        $service = $this->params->getRequired('service');
        $db = $this->db();
        $this->controls()->setTabs(new Tabs());
        $this->addSingleTab($this->translate('Configuration (read-only)'));
        $this->addTitle($this->translate('Services on %s'), $host->getObjectName());
        $content = $this->content();
        $table = IcingaHostServiceTable::load($host)
            ->setReadonly()
            ->highlightService($service)
            ->setTitle($this->translate('Individual Service objects'));

        if (count($table)) {
            $content->add($table);
        }

        /** @var IcingaHost[] $parents */
        $parents = IcingaTemplateRepository::instanceByObject($this->object)
            ->getTemplatesFor($this->object, true);
        foreach ($parents as $parent) {
            $table = IcingaHostServiceTable::load($parent)
                ->setReadonly()
                ->highlightService($service)
                ->setInheritedBy($host);
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
            $this->addHostServiceSetTables($parent, $host, $service);
        }

        $appliedSets = AppliedServiceSetLoader::fetchForHost($host);
        foreach ($appliedSets as $set) {
            $title = sprintf($this->translate('%s (Applied Service set)'), $set->getObjectName());

            $content->add(
                IcingaServiceSetServiceTable::load($set)
                    // ->setHost($host)
                    ->setAffectedHost($host)
                    ->setReadonly()
                    ->highlightService($service)
                    ->setTitle($title)
            );
        }

        $table = IcingaHostAppliedServicesTable::load($host)
            ->setReadonly()
            ->highlightService($service)
            ->setTitle($this->translate('Applied services'));

        if (count($table)) {
            $content->add($table);
        }
    }

    /**
     * @param IcingaHost $host
     * @param IcingaHost|null $affectedHost
     */
    protected function addHostServiceSetTables(IcingaHost $host, IcingaHost $affectedHost = null, $roService = null)
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
            $table = IcingaServiceSetServiceTable::load($set)
                ->setHost($host)
                ->setAffectedHost($affectedHost)
                ->setTitle($title);
            if ($roService) {
                $table->setReadonly()->highlightService($roService);
            }
            $this->content()->add($table);
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    public function servicesetserviceAction()
    {
        $db = $this->db();
        $host = $this->getHostObject();
        $serviceName = $this->params->get('service');
        $setParams = [
            'object_name' => $this->params->get('set'),
            'host_id'     => $host->get('id')
        ];
        $setTemplate = IcingaServiceSet::load($this->params->get('set'), $db);
        if (IcingaServiceSet::exists($setParams, $db)) {
            $set = IcingaServiceSet::load($setParams, $db);
        } else {
            $set = $setTemplate;
        }

        $service = IcingaService::load([
            'object_name'    => $serviceName,
            'service_set_id' => $setTemplate->get('id')
        ], $this->db());
        $service = IcingaService::create([
            'id'          => $service->get('id'),
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->get('id'),
            'imports'     => $service->listImportNames(),
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

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
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

                // Intentionally placed here, show it only for deployed Hosts
                $this->addOptionalInspectLink();
            }
        } catch (Exception $e) {
            // Silently ignore errors in the monitoring module
        }
    }

    protected function addOptionalInspectLink()
    {
        if (! $this->hasPermission('director/inspect')) {
            return;
        }

        $this->actions()->add(Link::create(
            $this->translate('Inspect'),
            'director/inspect/object',
            [
                'type'   => 'host',
                'plural' => 'hosts',
                'name'   => $this->object->getObjectName()
            ],
            [
                'class'            => 'icon-zoom-in',
                'data-base-target' => '_next'
            ]
        ));
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
