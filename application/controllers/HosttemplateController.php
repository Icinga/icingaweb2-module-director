<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Controller\TemplateController;

class HosttemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaHost::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
