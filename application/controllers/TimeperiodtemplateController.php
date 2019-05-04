<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Controller\TemplateController;

class TimeperiodtemplateController extends TemplateController
{
    protected function requireTemplate()
    {
        return IcingaTimePeriod::load([
            'object_name' => $this->params->get('name')
        ], $this->db());
    }
}
