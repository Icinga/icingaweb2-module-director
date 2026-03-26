<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class DatafieldCategoryDashlet extends Dashlet
{
    protected $icon = 'th-list';

    public function getTitle()
    {
        return $this->translate('Data Field Categories');
    }

    public function getSummary()
    {
        return $this->translate(
            'Categories bring structure to your Data Fields'
        );
    }

    public function getUrl()
    {
        return 'director/data/fieldcategories';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
