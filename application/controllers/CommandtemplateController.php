<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Web\Controller\TemplateController;

class CommandtemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaCommand::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
