<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class SelfServiceDashlet extends Dashlet
{
    protected $icon = 'chat';

    public function getTitle()
    {
        return $this->translate('Self Service API');
    }

    public function getSummary()
    {
        return $this->translate(
            'Icinga Director offers a Self Service API, allowing new Icinga'
            . ' nodes to register themselves'
        );
    }

    public function getUrl()
    {
        return 'director/settings/self-service';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
