<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Daemon;

use Icinga\Application\Logger as IcingaLogger;
use Icinga\Application\Logger\LogWriter;
use Icinga\Exception\ConfigurationError;

class Logger extends IcingaLogger
{
    public static function replaceRunningInstance(LogWriter $writer, $level = null)
    {
        try {
            $instance = static::$instance;
            if ($level !== null) {
                $instance->setLevel($level);
            }

            $instance->writer = $writer;
        } catch (ConfigurationError $e) {
            self::$instance->error($e->getMessage());
        }
    }
}
