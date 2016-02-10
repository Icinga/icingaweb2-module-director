<?php

namespace Icinga\Module\Director\Controllers;

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

    public function servicesAction()
    {
        $this->getTabs()->activate('services');
        $this->view->title = $this->translate('Services');
        $this->view->table = $this->loadTable('IcingaService')->enforceFilter('host_id', $this->object->id)->setConnection($this->db());
        $this->render('objects/table', null, true);
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
}
