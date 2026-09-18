<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Dashboard\Dashlet;

class NotificationCommandsDashlet extends CheckCommandsDashlet
{
    protected $icon = 'wrench';

    public function getSummary()
    {
        return $this->translate(
            'Notification Commands allow you to trigger any action you want when'
            . ' a notification takes place'
        );
    }

    public function getTitle()
    {
        return $this->translate('Notification Commands');
    }
}
