<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ExternalCheckCommandsDashlet extends CheckCommandsDashlet
{
    protected $icon = 'download';

    public function getSummary()
    {
        return $this->translate(
            'External Commands have been defined in your local Icinga 2'
            . ' Configuration.'
        );
    }

    public function getTitle()
    {
        return $this->translate('External Commands');
    }

    public function getUrl()
    {
        return 'director/commands?type=external_object';
    }
}
