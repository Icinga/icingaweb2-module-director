<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class CustomVariablesDashlet extends Dashlet
{
    protected $icon = 'edit';

    public function getTitle()
    {
        return $this->translate('Manage Custom Variables');
    }

    public function getSummary()
    {
        return $this->translate(
            'Manage the custom variables required for your configuration and make sure they fit your rules'
        );
    }

    public function getUrl()
    {
        return 'director/variables';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
