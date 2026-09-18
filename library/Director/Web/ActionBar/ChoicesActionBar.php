<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\ActionBar;

use gipfl\IcingaWeb2\Link;

class ChoicesActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        $this->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                "director/templatechoice/$type",
                ['type' => 'object'],
                [
                    'title' => $this->translate('Create a new template choice'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );
    }
}
