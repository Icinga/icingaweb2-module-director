<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
