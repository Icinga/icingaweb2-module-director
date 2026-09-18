<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
