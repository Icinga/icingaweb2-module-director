<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Objects\IcingaEndpoint;
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
        $this->view->table = $this->loadTable('IcingaHostService')
            ->setHost($host)
            ->enforceFilter('host_id', $host->id)
            ->setConnection($this->db());
    }

    public function agentAction()
    {
        $this->getTabs()->activate('agent');
        $this->view->title = 'Agent deployment instructions';
        // TODO: Fail when no ticket
        $this->view->certname = $this->object->object_name;
        $this->view->ticket = Util::getIcingaTicket($this->view->certname, $this->api()->getTicketSalt());
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

        return $this->sendJson(Util::getIcingaTicket($host->object_name, $this->api()->getTicketSalt()));
    }

    public function renderAction()
    {
        $this->renderAgentExtras();
        return parent::renderAction();
    }

    protected function renderAgentExtras()
    {
        $host = $this->object;
        $db = $this->db();
        if ($host->object_type !== 'object') {
            return;
        }

        if ($host->getResolvedProperty('has_agent') !== 'y') {
            return;
        }

        $name = $host->object_name;
        if (IcingaEndpoint::exists($name, $db)) {
            return;
        }

        $props = array(
            'object_name'  => $name,
            'object_type'  => 'object',
            'log_duration' => 0
        );
        if ($host->getResolvedProperty('master_should_connect') === 'y') {
            $props['host'] = $host->getResolvedProperty('address');
            $props['zone_id'] = $host->getResolvedProperty('zone_id');
        }

        $this->view->extraObjects = array(
            IcingaEndpoint::create($props),
            IcingaZone::create(array(
                'object_name' => $name,
                'parent'      => $db->getMasterZoneName()
            ), $db)->setEndpointList(array($name))
        );
    }
}
