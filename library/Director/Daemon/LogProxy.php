<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use Icinga\Module\Director\Db;
use function React\Promise\resolve;

class LogProxy implements DbBasedComponent
{
    protected $connection;

    protected $db;

    protected $server;

    protected $instanceUuid;

    protected $prefix = '';

    public function __construct($instanceUuid)
    {
        $this->instanceUuid = $instanceUuid;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param Db $connection
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();

        return resolve();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->connection = null;
        $this->db = null;

        return resolve();
    }

    public function log($severity, $message)
    {
        Logger::$severity($this->prefix . $message);
        /*
        // Not yet
        try {
            if ($this->db) {
                $this->db->insert('director_daemonlog', [
                    // environment/installation/db?
                    'instance_uuid' => $this->instanceUuid,
                    'ts_create'     => DaemonUtil::timestampWithMilliseconds(),
                    'level'         => $severity,
                    'message'       => $message,
                ]);
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
        }
        */
    }
}
