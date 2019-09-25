<?php

namespace Icinga\Module\Director\Daemon;

use Icinga\Application\Logger as IcingaLogger;
use Icinga\Application\Logger\LogWriter;
use Icinga\Exception\ConfigurationError;

class Logger extends IcingaLogger
{
    public static function replaceRunningInstance(LogWriter $writer, $level = self::DEBUG)
    {
        try {
            self::$instance
                ->setLevel($level)
                ->writer = $writer;
        } catch (ConfigurationError $e) {
            self::$instance->error($e->getMessage());
        }
    }
}
