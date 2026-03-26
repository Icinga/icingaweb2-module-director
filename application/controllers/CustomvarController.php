<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\CustomvarVariantsTable;

class CustomvarController extends ActionController
{
    public function variantsAction()
    {
        $varName = $this->params->getRequired('name');
        $this->addSingleTab($this->translate('Custom Variable'))
            ->addTitle($this->translate('Custom Variable variants: %s'), $varName);
        CustomvarVariantsTable::create($this->db(), $varName)->renderTo($this);
    }
}
