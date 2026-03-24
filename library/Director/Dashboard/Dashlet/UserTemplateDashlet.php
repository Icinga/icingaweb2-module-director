<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class UserTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = ['user'];

    public function getTitle()
    {
        return $this->translate('User Templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your User objects.')
            . ' ' . $this->getTemplateSummaryText('user');
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }

    public function getUrl()
    {
        return 'director/users/templates';
    }
}
