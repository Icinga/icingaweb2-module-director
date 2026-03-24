<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class SingleServicesDashlet extends Dashlet
{
    protected $icon = 'service';

    public function getTitle()
    {
        return $this->translate('Single Services');
    }

    public function getSummary()
    {
        return $this->translate(
            'Here you can find all single services directly attached to single'
            . ' hosts'
        );
    }

    public function getUrl()
    {
        return 'director/services';
    }

    public function listRequiredPermissions()
    {
        return [Permission::SERVICES];
    }
}
