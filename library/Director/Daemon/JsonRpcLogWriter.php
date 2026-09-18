<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Daemon;

use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use Icinga\Application\Logger\LogWriter;
use Icinga\Data\ConfigObject;

class JsonRpcLogWriter extends LogWriter
{
    protected $connection;

    protected static $severityMap = [
        Logger::DEBUG   => 'debug',
        Logger::INFO    => 'info',
        Logger::WARNING => 'warning',
        Logger::ERROR   => 'error',
    ];

    public function __construct(Connection $connection)
    {
        parent::__construct(new ConfigObject([]));
        $this->connection = $connection;
    }

    public function log($severity, $message)
    {
        $message = \iconv('UTF-8', 'UTF-8//IGNORE', $message);
        $this->connection->sendNotification(
            Notification::create('logger.log', [
                static::$severityMap[$severity],
                $message
            ])
        );
    }
}
