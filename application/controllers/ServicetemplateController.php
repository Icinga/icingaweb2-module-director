<?php

namespace Icinga\Module\Director\Controllers;

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
}
