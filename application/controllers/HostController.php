<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ObjectController;

class HostController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object) {
            $tabs = $this->getTabs();
            $tabs->add('services', array(
                'url'       => 'director/host/services',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Services'
            ));
            try {
                if ($this->object->object_type === 'object'
                    && $this->object->getResolvedProperty('has_agent') === 'y'
                ) {
                    $tabs->add('agent', array(
                        'url'       => 'director/host/agent',
                        'urlParams' => array('name' => $this->object->object_name),
                        'label'     => 'Agent'
                    ));
                }
            } catch (NestingError $e) {
                // Ignore nesting errors
            }
        }
    }

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/hosts');
    }

    public function editAction()
    {
        parent::editAction();
        $host = $this->object;
        $mon = $this->monitoring();
        if ($host->isObject() && $mon->isAvailable() && $mon->hasHost($host->object_name)) {
            $this->view->actionLinks .= ' ' . $this->view->qlink(
                $this->translate('Show'),
                'monitoring/host/show',
                array('host' => $host->object_name),
                array(
                    'class'            => 'icon-globe critical',
                    'data-base-target' => '_next'
                )
            );
        }
    }

    public function servicesAction()
    {
        $db = $this->db();
        $host = $this->object;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add service'),
            'director/service/add',
            array('host' => $host->object_name),
            array('class' => 'icon-plus')
        ) . ' ' .  $this->view->qlink(
            $this->translate('Add service set'),
            'director/serviceset/add',
            array('host' => $host->object_name),
            array('class' => 'icon-plus')
        );

        $this->getTabs()->activate('services');
        $this->view->title = sprintf(
            $this->translate('Services: %s'),
            $host->object_name
        );

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
            $this->addHostServiceSetTables($host, $tables);
        }

        $title = $this->translate('Applied services');
        $table = $this->loadTable('IcingaHostAppliedServices')
            ->setHost($host)
            ->setTitle($title)
            ->setConnection($db);

        $tables[$title] = $table;

        $this->view->tables = $tables;
    }

    protected function addHostServiceSetTables(IcingaHost $host, & $tables)
    {
        $db = $this->db();

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
                ->setTitle($title)
                ->setConnection($db);

            $tables[$title] = $table;
        }
    }

    public function appliedserviceAction()
    {
        $db = $this->db();
        /** @var IcingaHost $host */
        $host = $this->object;
        $serviceId = $this->params->get('service_id');
        $parent = IcingaService::loadWithAutoIncId($serviceId, $db);
        $serviceName = $parent->object_name;

        $service = IcingaService::create(array(
            'imports'     => $parent,
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ), $db);

        $this->view->title = sprintf(
            $this->translate('Applied service: %s'),
            $serviceName
        );

        $this->view->form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setApplyGenerated($parent)
            ->setObject($service)
            ;

        $this->commonForServices();
    }

    public function inheritedserviceAction()
    {
        $db = $this->db();
        $host = $this->object;
        $serviceName = $this->params->get('service');
        $from = IcingaHost::load($this->params->get('inheritedFrom'), $this->db());

        $parent = IcingaService::load(
            array(
                'object_name' => $serviceName,
                'host_id'     => $from->id
            ),
            $this->db()
        );

        // TODO: we want to eventually show the host template name, doesn't work
        //       as template resolution would break.
        // $parent->object_name = $from->object_name;

        $service = IcingaService::create(array(
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'imports'     => array($parent),
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ), $db);

        $this->view->title = sprintf(
            $this->translate('Inherited service: %s'),
            $serviceName
        );

        $this->view->form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setInheritedFrom($from->object_name)
            ->setObject($service);

        // TODO: figure out whether this has any effect
        // $this->view->form->setResolvedImports();
        $this->commonForServices();
    }

    public function servicesetserviceAction()
    {
        $db = $this->db();
        /** @var IcingaHost $host */
        $host = $this->object;
        $serviceName = $this->params->get('service');
        $set = IcingaServiceSet::load($this->params->get('set'), $db);

        $service = IcingaService::load(
            array(
                'object_name'    => $serviceName,
                'service_set_id' => $set->get('id')
            ),
            $this->db()
        );
        $service = IcingaService::create(array(
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'imports'     => array($service),
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ), $db);

        // $set->copyVarsToService($service);
        $this->view->title = sprintf(
            $this->translate('%s on %s (from set: %s)'),
            $serviceName,
            $host->getObjectName(),
            $set->getObjectName()
        );

        $this->getTabs()->activate('services');

        $this->view->form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setServiceSet($set)
            ->setObject($service);
        // $this->view->form->setResolvedImports();
        $this->view->form->handleRequest();
        $this->commonForServices();
    }

    protected function commonForServices()
    {
        $host = $this->object;
        $this->view->actionLinks = $this->view->qlink(
            $this->translate('back'),
            'director/host/services',
            array('name' => $host->object_name),
            array('class' => 'icon-left-big')
        );
        $this->getTabs()->activate('services');
        $this->view->form->handleRequest();
        $this->setViewScript('object/form');
    }

    public function agentAction()
    {
        switch ($this->params->get('download')) {
            case 'windows-kickstart':
                header('Content-type: application/octet-stream');
                header('Content-Disposition: attachment; filename=icinga2-agent-kickstart.ps1');

                $wizard = $this->view->wizard = new AgentWizard($this->object);
                $wizard->setTicketSalt($this->api()->getTicketSalt());
                echo preg_replace('/\n/', "\r\n", $wizard->renderWindowsInstaller());
                exit;
            case 'linux':
                header('Content-type: application/octet-stream');
                header('Content-Disposition: attachment; filename=icinga2-agent-kickstart.bash');

                $wizard = $this->view->wizard = new AgentWizard($this->object);
                $wizard->setTicketSalt($this->api()->getTicketSalt());
                echo $wizard->renderLinuxInstaller();
                exit;
        }

        $this->gracefullyActivateTab('agent');
        $this->view->title = 'Agent deployment instructions';
        // TODO: Fail when no ticket
        $this->view->certname = $this->object->object_name;

        try {
            $this->view->ticket = Util::getIcingaTicket(
                $this->view->certname,
                $this->api()->getTicketSalt()
            );

            $wizard = $this->view->wizard = new AgentWizard($this->object);
            $wizard->setTicketSalt($this->api()->getTicketSalt());
            $this->view->windows = $wizard->renderWindowsInstaller();
            $this->view->linux = $wizard->renderLinuxInstaller();
        } catch (Exception $e) {
            $this->view->ticket = 'ERROR';
            $this->view->error = sprintf(
                $this->translate(
                    'A ticket for this agent could not have been requested from'
                    . ' your deployment endpoint: %s'
                ),
                $e->getMessage()
            );
        }

        $this->view->master = $this->db()->getDeploymentEndpointName();
        $this->view->masterzone = $this->db()->getMasterZoneName();
        $this->view->globalzone = $this->db()->getDefaultGlobalZoneName();
    }

    public function ticketAction()
    {
        if (! $this->getRequest()->isApiRequest() || ! $this->object) {
            throw new NotFoundError('Not found');
        }

        $host = $this->object;
        if ($host->getResolvedProperty('has_agent') !== 'y') {
            throw new NotFoundError('The host "%s" is not an agent', $host->object_name);
        }

        return $this->sendJson(
            Util::getIcingaTicket(
                $host->object_name,
                $this->api()->getTicketSalt()
            )
        );
    }
}
