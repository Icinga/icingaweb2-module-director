<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Controller\SimpleController;
use Icinga\Module\Director\Web\Table\ServicesOnHostsTable;

class ServicetemplateController extends SimpleController
{
    public function hostsAction()
    {
        $this->addSingleTab($this->translate('Hosts using this service Template'));
        $this->content()->add(
            new ServicesOnHostsTable($this->db())
        );
    }

    public function servicesAction()
    {
        $template = $this->requireTemplate();
        $this->addSingleTab(
            $this->translate('Single Services')
        )->addTitle(
            $this->translate('Services based on %s'),
            $template->getObjectName()
        );

        $this->content()->add(
            new ServicesOnHostsTable($this->db())
        );
    }

    public function usageAction()
    {
        $template = $this->requireTemplate();

        $this->addSingleTab(
            $this->translate('Service Template Usage')
        )->addTitle(
            $this->translate('Template: %s'),
            $template->getObjectName()
        );
    }

    protected function requireTemplate()
    {
        return IcingaService::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
