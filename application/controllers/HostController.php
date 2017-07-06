<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\AgentWizard;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Html\Link;

class HostController extends ObjectController
{
    public function init()
    {
        parent::init();
        if ($this->object) {
            $tabs = $this->tabs();
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
        return new HostgroupRestriction($this->db(), $this->Auth());
    }

    /**
     * @param IcingaHost $object
     * @return bool
     */
/*    protected function allowsObject(IcingaObject $object)
    {
        return $this->getHostgroupRestriction()->allowsHost($object);
    }
*/
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
                    array('host' => $host->object_name),
                    array(
                        'class'            => 'icon-globe critical',
                        'data-base-target' => '_next'
                    )
                ));
            }
        } catch (Exception $e) {
            // Silently ignore errors in the monitoring module
        }
    }

    public function servicesAction()
    {
        $db = $this->db();
        $host = $this->object;

        $this->tabs()->activate('services');
        $this->addTitle($this->translate('Services: %s'), $host->object_name);

        $this->actions()->add(Link::create(
            $this->translate('Add service'),
            'director/service/add',
            ['host' => $host->object_name],
            ['class' => 'icon-plus']
        ))->add(Link::create(
            $this->translate('Add service set'),
            'director/serviceset/add',
            ['host' => $host->object_name],
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

        $this->addTitle(
            $this->translate('Applied service: %s'),
            $serviceName
        );

        $this->content()->add(
            $this->loadForm('IcingaService')
                ->setDb($db)
                ->setHost($host)
                ->setApplyGenerated($parent)
                ->setObject($service)
        );

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

        $this->addTitle($this->translate('Inherited service: %s'), $serviceName);
        $form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setInheritedFrom($from->object_name)
            ->setObject($service);
        $form->handleRequest();
        $this->content()->add($form);
        $this->commonForServices();
        // TODO: figure out whether this has any effect
        // $form->setResolvedImports();
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
        $this->addTitle(
            $this->translate('%s on %s (from set: %s)'),
            $serviceName,
            $host->getObjectName(),
            $set->getObjectName()
        );

        $form = $this->loadForm('IcingaService')
            ->setDb($db)
            ->setHost($host)
            ->setServiceSet($set)
            ->setObject($service);
        $form->handleRequest();
        $this->getTabs()->activate('services');
        $this->content()->add($form);
        // $form->setResolvedImports();
        $this->commonForServices();
    }

    protected function commonForServices()
    {
        $host = $this->object;
        $this->actions()->add(Link::create(
            $this->translate('back'),
            'director/host/services',
            ['name' => $host->object_name],
            ['class' => 'icon-left-big']
        ));
        $this->getTabs()->activate('services');
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

        $c = $this->content();
        $docBaseUrl = 'https://docs.icinga.com/icinga2/latest/doc/module/icinga2/chapter/distributed-monitoring';
        $sectionSetup = 'distributed-monitoring-setup-satellite-client';
        $sectionTopDown = 'distributed-monitoring-top-down';
        $c->add(Html::p()->addPrintf(
            'Please check the %s for more related information.'
            . ' The Director-assisted setup corresponds to configuring a %s environment.',
            Html::a(
                ['href' => $docBaseUrl . '#' . $sectionSetup],
                $this->translate('Icinga 2 Client documentation')
            ),
            Html::a(
                ['href' => $docBaseUrl . '#' . $sectionTopDown],
                $this->translate('Top Down')
            )
        ));

        $this->tabs()->activate('agent');
        $this->addTitle('Agent deployment instructions');
        $certname = $this->object->object_name;

        try {
            $ticket = Util::getIcingaTicket($certname, $this->api()->getTicketSalt());
            $wizard = new AgentWizard($this->object);
            $wizard->setTicketSalt($this->api()->getTicketSalt());
        } catch (Exception $e) {
            $c->add(Html::p(['class' => 'error'], sprintf(
                $this->translate(
                    'A ticket for this agent could not have been requested from'
                    . ' your deployment endpoint: %s'
                ),
                $e->getMessage()
            )));

            return;
        }

        // TODO: move to CSS
        $codeStyle = ['style' => 'background: black; color: white; height: 14em; overflow: scroll;'];
        $c->add([
            Html::h2($this->translate('For manual configuration')),
            Html::p($this->translate('Ticket'), ': ', Html::code($ticket)),
            Html::h2($this->translate('Windows Kickstart Script')),
            Link::create(
                $this->translate('Download'),
                $this->url()->with('download', 'windows-kickstart'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),
            Html::pre($codeStyle, $wizard->renderWindowsInstaller()),
            Html::p($this->translate(
                'This requires the Icinga Agent to be installed. It generates and signs'
                . ' it\'s certificate and it also generates a minimal icinga2.conf to get'
                . ' your agent connected to it\'s parents'
            )),
            Html::h2($this->translate('Linux commandline')),
            Link::create(
                $this->translate('Download'),
                $this->url()->with('download', 'linux'),
                null,
                ['class' => 'icon-download', 'target' => '_blank']
            ),
            Html::p($this->translate('Just download and run this script on your Linux Client Machine:')),
            Html::pre($codeStyle, $wizard->renderLinuxInstaller())
        ]);
    }

    protected function handleApiRequest()
    {
        // TODO: I hate doing this:
        if ($this->getRequest()->getActionName() === 'ticket') {
            $host = $this->object;

            if ($host->getResolvedProperty('has_agent') !== 'y') {
                throw new NotFoundError('The host "%s" is not an agent', $host->object_name);
            }

            $this->sendJson(
                $this->getResponse(),
                Util::getIcingaTicket(
                    $host->object_name,
                    $this->api()->getTicketSalt()
                )
            );
            return;
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
