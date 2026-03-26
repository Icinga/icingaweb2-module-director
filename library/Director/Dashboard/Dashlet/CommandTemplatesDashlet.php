<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

class CommandTemplatesDashlet extends CheckCommandsDashlet
{
    protected $icon = 'cubes';

    public function getSummary()
    {
        return $this->translate(
            'External Notification Commands have been defined in your local Icinga 2'
            . ' Configuration.'
        );
    }

    public function getTitle()
    {
        return $this->translate('Command Templates');
    }

    public function getUrl()
    {
        return 'director/commands/templates';
    }
}
