<?php

namespace Icinga\Module\Director\Daemon;

use Icinga\Application\Logger\LogWriter;
use Icinga\Data\ConfigObject;

class SystemdLogWriter extends LogWriter
{
    protected static $severityMap = [
        Logger::DEBUG   => 7,
        Logger::INFO    => 6,
        Logger::WARNING => 4,
        Logger::ERROR   => 3,
    ];

    public function __construct()
    {
        parent::__construct(new ConfigObject([]));
    }

    public function log($severity, $message)
    {
        $severity = self::$severityMap[$severity];
        echo "<$severity>$message\n";
    }
}
