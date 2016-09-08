<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaZone;
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
            if ($this->object->object_type === 'object'
                && $this->object->getResolvedProperty('has_agent') === 'y'
            ) {
                $tabs->add('agent', array(
                    'url'       => 'director/host/agent',
                    'urlParams' => array('name' => $this->object->object_name),
                    'label'     => 'Agent'
                ));
            }
        }
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

        foreach ($resolver->fetchResolvedParents() as $parent) {
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

        $this->view->tables = $tables;
    }

    public function appliedserviceAction()
    {
        $db = $this->db();
        $host = $this->object;
        $serviceName = $this->params->get('service');

        $applied = $host->vars()->get($db->settings()->magic_apply_for);

        $props = $applied->{$serviceName};

        $parent = IcingaService::create(array(
            'object_type' => 'template',
            'object_name' => $this->translate('Host'),
        ), $db);

        if (isset($props->vars)) {
            $parent->vars = $props->vars->getValue();
        }

        $service = IcingaService::create(array(
            'object_type' => 'apply',
            'object_name' => $serviceName,
            'host_id'     => $host->id,
            'vars'        => $host->getOverriddenServiceVars($serviceName),
        ), $db);


        if (isset($props->templates) && $templates = $props->templates->getValue()) {
            $imports = $templates;
        } else {
            $imports = $serviceName;
        }

        if (! is_array($imports)) {
            $imports = array($imports);
        }

        // TODO: Validation for $imports? They might not exist!
        array_push($imports, $parent);
        $service->imports = $imports;

        $this->view->title = sprintf(
            $this->translate('Applied service: %s'),
            $serviceName
        );

        $this->getTabs()->activate('services');

        $this->view->form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setHostGenerated()
            ->setObject($service)
            ->handleRequest()
            ;

        $this->setViewScript('object/form');
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

        $parent->object_name = $from->object_name;

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

        $this->getTabs()->activate('services');

        $this->view->form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setInheritedFrom($from->object_name)
            ->setObject($service)
            ->handleRequest()
            ;

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
