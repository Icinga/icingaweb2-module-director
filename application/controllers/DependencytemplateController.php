<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaDependency;
use Icinga\Module\Director\Web\Controller\TemplateController;

class DependencytemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaDependency::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
