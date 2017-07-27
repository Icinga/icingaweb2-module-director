<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Web\Controller\TemplateController;

class UsertemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaUser::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
