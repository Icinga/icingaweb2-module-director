<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Restriction\BetaHostgroupRestriction;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Web\Url;

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

    protected function loadRestrictions()
    {
        return array(
            $this->getHostgroupRestriction()
        );
    }

    protected function getHostgroupRestriction()
    {
        return new BetaHostgroupRestriction($this->db(), $this->Auth());
    }

    /**
     * @param IcingaHost $object
     * @return bool
     */
    protected function allowsObject(IcingaObject $object)
    {
        return $this->getHostgroupRestriction()->allowsHost($object);
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

        $this->view->tables = $tables;
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
        )->where('si.parent_service_set_id = ?', $this->params->get('setId'))
        ->where('ss.host_id = ?', $this->object->id);

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
        if ($os = $this->params->get('download')) {
            $wizard = new AgentWizard($this->object);
            $wizard->setTicketSalt($this->api()->getTicketSalt());

            switch ($os) {
                case 'windows-kickstart':
                    $ext = 'ps1';
                    $script = preg_replace('/\n/', "\r\n", $wizard->renderWindowsInstaller());
                    break;
                case 'linux':
                    $ext = 'bash';
                    $script = $wizard->renderLinuxInstaller();
                    break;
                default:
                    throw new NotFoundError('There is no kickstart helper for %s', $os);
            }

            header('Content-type: application/octet-stream');
            header('Content-Disposition: attachment; filename=icinga2-agent-kickstart.' . $ext);
            echo $script;
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

    protected function handleApiRequest()
    {
        // TODO: I hate doing this:
        if ($this->getRequest()->getActionName() === 'ticket') {
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

        return parent::handleApiRequest();
    }

    public function ticketAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            throw new NotFoundError('Not found');
        }
    }
}
