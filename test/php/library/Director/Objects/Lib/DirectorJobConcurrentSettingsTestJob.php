<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects\Lib;

use Icinga\Module\Director\Hook\JobHook;

class DirectorJobConcurrentSettingsTestJob extends JobHook
{
    public static $duringRun;

    public function run()
    {
        if (self::$duringRun !== null) {
            (self::$duringRun)();
        }
    }
}
