<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;

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
            $tabs->add('dependencies', array(
                'url'       => 'director/host/dependencies',
                'urlParams' => array('name' => $this->object->object_name),
                'label'     => 'Dependencies'
            ));
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
        $this->gracefullyActivateTab('agent');
        $this->view->title = 'Agent deployment instructions';
        // TODO: Fail when no ticket
        $this->view->certname = $this->object->object_name;

        try {
            $this->view->ticket = Util::getIcingaTicket(
                $this->view->certname,
                $this->api()->getTicketSalt()
            );

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
    public function dependenciesAction()
    {
        $host = $this->object;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add dependency'),
            'director/dependency/add',
            array('host' => $host->object_name),
            array('class' => 'icon-plus')
        );

        $this->getTabs()->activate('dependencies');
        $this->view->title = sprintf(
            $this->translate('Dependencies: %s'),
            $host->object_name
        );

	//TODO fileter services?  null if for host ?

        $this->view->table = $this->loadTable('IcingaHostDependency')
            ->setHost($host)
            ->enforceFilter('child_host_id', $host->id)
            ->setConnection($this->db());
    }


}
