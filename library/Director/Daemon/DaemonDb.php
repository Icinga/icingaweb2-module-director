<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use gipfl\IcingaCliDaemon\DbResourceConfigWatch;
use gipfl\IcingaCliDaemon\RetryUnless;
use Icinga\Data\ConfigObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use ipl\Stdlib\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\RejectedPromise;
use RuntimeException;
use SplObjectStorage;

class DaemonDb
{
    use EventEmitter;

    /** @var LoopInterface */
    private $loop;

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var DaemonProcessDetails */
    protected $details;

    /** @var DbBasedComponent[] */
    protected $registeredComponents = [];

    /** @var DbResourceConfigWatch|null */
    protected $configWatch;

    /** @var array|null */
    protected $dbConfig;

    /** @var RetryUnless|null */
    protected $pendingReconnection;

    /** @var Deferred|null */
    protected $pendingDisconnect;

    /** @var \React\EventLoop\TimerInterface */
    protected $refreshTimer;

    /** @var \React\EventLoop\TimerInterface */
    protected $schemaCheckTimer;

    /** @var int */
    protected $startupSchemaVersion;

    public function __construct(DaemonProcessDetails $details, $dbConfig = null)
    {
        $this->details = $details;
        $this->dbConfig = $dbConfig;
    }

    public function register(DbBasedComponent $component)
    {
        $this->registeredComponents[] = $component;

        return $this;
    }

    public function setConfigWatch(DbResourceConfigWatch $configWatch)
    {
        $this->configWatch = $configWatch;
        $configWatch->notify(function ($config) {
            $this->disconnect()->then(function () use ($config) {
                return $this->onNewConfig($config);
            });
        });
        if ($this->loop) {
            $configWatch->run($this->loop);
        }

        return $this;
    }

    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->connect();
        $this->refreshTimer = $loop->addPeriodicTimer(3, function () {
            $this->refreshMyState();
        });
        $this->schemaCheckTimer = $loop->addPeriodicTimer(15, function () {
            $this->checkDbSchema();
        });
        if ($this->configWatch) {
            $this->configWatch->run($this->loop);
        }
    }

    protected function onNewConfig($config)
    {
        if ($config === null) {
            if ($this->dbConfig === null) {
                Logger::error('DB configuration is not valid');
            } else {
                Logger::error('DB configuration is no longer valid');
            }
            $this->emitStatus('no configuration');
            $this->dbConfig = $config;

            return new FulfilledPromise();
        } else {
            $this->emitStatus('configuration loaded');
            $this->dbConfig = $config;

            return $this->establishConnection($config);
        }
    }

    protected function establishConnection($config)
    {
        if ($this->connection !== null) {
            Logger::error('Trying to establish a connection while being connected');
            return new RejectedPromise();
        }
        $callback = function () use ($config) {
            $this->reallyEstablishConnection($config);
        };
        $onSuccess = function () {
            $this->pendingReconnection = null;
            $this->onConnected();
        };
        if ($this->pendingReconnection) {
            $this->pendingReconnection->reset();
            $this->pendingReconnection = null;
        }
        $this->emitStatus('connecting');

        return $this->pendingReconnection = RetryUnless::succeeding($callback)
            ->setInterval(0.2)
            ->slowDownAfter(10, 10)
            ->run($this->loop)
            ->then($onSuccess)
            ;
    }

    protected function reallyEstablishConnection($config)
    {
        $connection = new Db(new ConfigObject($config));
        $connection->getDbAdapter()->getConnection();
        $migrations = new Migrations($connection);
        if (! $migrations->hasSchema()) {
            $this->emit('status', ['DB has no schema', 'error']);
            throw new RuntimeException('DB has no schema');
        }
        $this->wipeOrphanedInstances($connection);
        if ($this->hasAnyOtherActiveInstance($connection)) {
            throw new RuntimeException('DB is locked by a running daemon instance');
        }
        $this->startupSchemaVersion = $migrations->getLastMigrationNumber();
        $this->details->set('schema_version', $this->startupSchemaVersion);

        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->loop->futureTick(function () {
            $this->refreshMyState();
        });

        return $connection;
    }

    protected function checkDbSchema()
    {
        if ($this->connection === null) {
            return;
        }

        if ($this->schemaIsOutdated()) {
            $this->emit('schemaChange', [
                $this->getStartupSchemaVersion(),
                $this->getDbSchemaVersion()
            ]);
        }
    }

    protected function schemaIsOutdated()
    {
        return $this->getStartupSchemaVersion() < $this->getDbSchemaVersion();
    }

    protected function getStartupSchemaVersion()
    {
        return $this->startupSchemaVersion;
    }

    protected function getDbSchemaVersion()
    {
        if ($this->connection === null) {
            throw new RuntimeException(
                'Cannot determine DB schema version without an established DB connection'
            );
        }
        $migrations = new Migrations($this->connection);

        return  $migrations->getLastMigrationNumber();
    }

    protected function onConnected()
    {
        $this->emitStatus('connected');
        Logger::info('Connected to the database');
        foreach ($this->registeredComponents as $component) {
            $component->initDb($this->connection);
        }
    }

    /**
     * @return \React\Promise\PromiseInterface
     */
    protected function reconnect()
    {
        return $this->disconnect()->then(function () {
            return $this->connect();
        }, function (Exception $e) {
            Logger::error('Disconnect failed. This should never happen: ' . $e->getMessage());
            exit(1);
        });
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function connect()
    {
        if ($this->connection === null) {
            if ($this->dbConfig) {
                return $this->establishConnection($this->dbConfig);
            }
        }

        return new FulfilledPromise();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function disconnect()
    {
        if (! $this->connection) {
            return new FulfilledPromise();
        }
        if ($this->pendingDisconnect) {
            return $this->pendingDisconnect->promise();
        }

        $this->eventuallySetStopped();
        $this->pendingDisconnect = new Deferred();
        $pendingComponents = new SplObjectStorage();
        foreach ($this->registeredComponents as $component) {
            $pendingComponents->attach($component);
            $resolve = function () use ($pendingComponents, $component) {
                $pendingComponents->detach($component);
                if ($pendingComponents->count() === 0) {
                    $this->pendingDisconnect->resolve();
                }
            };
            // TODO: What should we do in case they don't?
            $component->stopDb()->then($resolve);
        }

        try {
            if ($this->db) {
                $this->db->closeConnection();
            }
        } catch (Exception $e) {
            Logger::error('Failed to disconnect: ' . $e->getMessage());
        }

        return $this->pendingDisconnect->promise()->then(function () {
            $this->connection = null;
            $this->db = null;
            $this->pendingDisconnect = null;
        });
    }

    protected function emitStatus($message, $level = 'info')
    {
        $this->emit('state', [$message, $level]);

        return $this;
    }

    protected function hasAnyOtherActiveInstance(Db $connection)
    {
        $db = $connection->getDbAdapter();

        return (int) $db->fetchOne(
            $db->select()
                ->from('director_daemon_info', 'COUNT(*)')
                ->where('ts_stopped IS NULL')
        ) > 0;
    }

    protected function wipeOrphanedInstances(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $db->delete('director_daemon_info', 'ts_stopped IS NOT NULL');
        $db->delete('director_daemon_info', $db->quoteInto(
            'instance_uuid_hex = ?',
            $this->details->getInstanceUuid()
        ));
        $count = $db->delete(
            'director_daemon_info',
            'ts_stopped IS NULL AND ts_last_update < ' . (
                DaemonUtil::timestampWithMilliseconds() - (60 * 1000)
            )
        );
        if ($count > 1) {
            Logger::error("Removed $count orphaned daemon instance(s) from DB");
        }
    }

    protected function refreshMyState()
    {
        if ($this->db === null || $this->pendingReconnection || $this->pendingDisconnect) {
            return;
        }
        try {
            $updated = $this->db->update(
                'director_daemon_info',
                $this->details->getPropertiesToUpdate(),
                $this->db->quoteInto('instance_uuid_hex = ?', $this->details->getInstanceUuid())
            );

            if (! $updated) {
                $this->db->insert(
                    'director_daemon_info',
                    $this->details->getPropertiesToInsert()
                );
            }
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            $this->reconnect();
        }
    }

    protected function eventuallySetStopped()
    {
        try {
            if (! $this->db) {
                return;
            }
            $this->db->update(
                'director_daemon_info',
                ['ts_stopped' => DaemonUtil::timestampWithMilliseconds()],
                $this->db->quoteInto('instance_uuid_hex = ?', $this->details->getInstanceUuid())
            );
        } catch (Exception $e) {
            Logger::error('Failed to update daemon info (setting ts_stopped): ' . $e->getMessage());
        }
    }
}
