<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\SimpleController;
use Icinga\Module\Director\Web\Table\ServiceApplyRulesTable;
use Icinga\Module\Director\Web\Table\ServicesOnHostsTable;
use Icinga\Module\Director\Web\Table\ServiceTemplatesTable;
use ipl\Html\Link;

class ServicetemplatesController extends SimpleController
{
    public function indexAction()
    {
        $this->addSingleTab($this->translate('Service Templates'));
        if ($this->quickSearch()) {
            // Not yet
        }

        $this->addTitle($this->translate('All your Service Templates'));
        $this->actions()->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                'director/service/add',
                ['type' => 'template'],
                [
                    'title' => $this->translate('Create a new Service Template'),
                    'class' => 'icon-plus'
                ]
            )
        );

        $this->content()->add(
            new ServiceTemplatesTable($this->db())
        );
    }

    // TODO: dedicated controller
    public function applyrulesAction()
    {
        $this->addSingleTab($this->translate('Service Apply Rules'));
        if ($this->quickSearch()) {
            // Not yet
        }

        $this->addTitle($this->translate('All your Service Apply Rules'));
        $this->actions()->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                'director/service/add',
                ['type' => 'apply_rule'],
                [
                    'title' => $this->translate('Create a new Service Apply Rule'),
                    'class' => 'icon-plus'
                ]
            )
        );

        $this->content()->add(
            new ServiceApplyRulesTable($this->db())
        );
    }

    /**
     * TODO: This should be director/services once it has REST API support
     */
    public function servicesAction()
    {
        $this->addSingleTab(
            $this->translate('Single Services')
        )->addTitle(
            $this->translate('Single Services configured for your hosts')
        );

        $this->content()->add(
            new ServicesOnHostsTable($this->db())
        );
    }

    protected function getBackToDashboardLink()
    {
        return Link::create(
            $this->translate('back'),
            'director/dashboard',
            ['name' => 'services'],
            [
                'title' => $this->translate('Go back to Services Dashboard'),
                'class' => 'icon-left-big',
                'data-base-target' => '_main'
            ]
        );
    }
}
