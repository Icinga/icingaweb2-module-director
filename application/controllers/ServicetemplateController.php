<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Controller\TemplateController;

class ServicetemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaService::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
