<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;

class CommandsController extends ObjectsController
{
    public function indexAction()
    {
        parent::indexAction();
        $validTypes = ['object', 'external_object'];
        $type = $this->params->get('type', 'object');
        if (! in_array($type, $validTypes)) {
            $type = 'object';
        }

        $this->table->setType($type);
    }
}
