<?php

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
