<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\ActionBar;

use gipfl\IcingaWeb2\Link;

class ObjectsActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        $this->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                "director/$type/add",
                ['type' => 'object'],
                [
                    'title' => $this->translate('Create a new object'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );
    }
}
