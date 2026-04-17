<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use gipfl\Cli\Process;
use gipfl\IcingaCliDaemon\DbResourceConfigWatch;
use gipfl\SystemD\NotifySystemD;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use Ramsey\Uuid\Uuid;

class BackgroundDaemon
{
    /** @var LoopInterface */
    private $loop;

    /** @var NotifySystemD|boolean */
    protected $systemd;

    /** @var JobRunner */
    protected $jobRunner;

    /** @var string|null */
    protected $dbResourceName;

    /** @var DaemonDb */
    protected $daemonDb;

    /** @var DaemonProcessState */
    protected $processState;

    /** @var DaemonProcessDetails */
    protected $processDetails;

    /** @var LogProxy */
    protected $logProxy;

    /** @var bool */
    protected $reloading = false;

    /** @var bool */
    protected $shuttingDown = false;

    public function run(?LoopInterface $loop = null)
    {
        if ($ownLoop = ($loop === null)) {
            $loop = Loop::create();
        }
        $this->loop = $loop;
        $this->loop->futureTick(function () {
            $this->initialize();
        });
        if ($ownLoop) {
            $loop->run();
        }
    }

    public function setDbResourceName($name)
    {
        $this->dbResourceName = $name;

        return $this;
    }

    protected function initialize()
    {
        $this->registerSignalHandlers($this->loop);
        $this->processState = new DaemonProcessState('icinga::director');
        $this->jobRunner = new JobRunner($this->loop);
        $this->systemd = $this->eventuallyInitializeSystemd();
        $this->processState->setSystemd($this->systemd);
        if ($this->systemd) {
            $this->systemd->setReady();
        }
        $this->setState('ready');
        $this->processDetails = $this
            ->initializeProcessDetails($this->systemd)
            ->registerProcessList($this->jobRunner->getProcessList());
        $this->logProxy = new LogProxy($this->processDetails->getInstanceUuid());
        $this->jobRunner->forwardLog($this->logProxy);
        $this->daemonDb = $this->initializeDb(
            $this->processDetails,
            $this->processState,
            $this->dbResourceName
        );
        $this->daemonDb
            ->register($this->jobRunner)
            ->register($this->logProxy)
            ->register(new DeploymentChecker($this->loop))
            ->run($this->loop);
        $this->setState('running');
    }

    /**
     * @param NotifySystemD|false $systemd
     * @return DaemonProcessDetails
     */
    protected function initializeProcessDetails($systemd)
    {
        if ($systemd && $systemd->hasInvocationId()) {
            $uuid = $systemd->getInvocationId();
        } else {
            try {
                $uuid = \bin2hex(Uuid::uuid4()->getBytes());
            } catch (Exception $e) {
                $uuid = 'deadc0de' . substr(md5((string) getmypid()), 0, 24);
            }
        }
        $processDetails = new DaemonProcessDetails($uuid);
        if ($systemd) {
            $processDetails->set('running_with_systemd', 'y');
        }

        return $processDetails;
    }

    protected function eventuallyInitializeSystemd()
    {
        $systemd = NotifySystemD::ifRequired($this->loop);
        if ($systemd) {
            Logger::replaceRunningInstance(new SystemdLogWriter());
            Logger::info(sprintf(
                "Started by systemd, notifying watchdog every %0.2Gs via %s",
                $systemd->getWatchdogInterval(),
                $systemd->getSocketPath()
            ));
        } else {
            Logger::debug('Running without systemd');
        }

        return $systemd;
    }

    /**
     * @return DaemonProcessDetails
     */
    public function getProcessDetails()
    {
        return $this->processDetails;
    }

    /**
     * @return DaemonProcessState
     */
    public function getProcessState()
    {
        return $this->processState;
    }

    protected function initializeDb(
        DaemonProcessDetails $processDetails,
        DaemonProcessState $processState,
        $dbResourceName = null
    ) {
        $db = new DaemonDb($processDetails);
        $db->on('state', function ($state, $level = null) use ($processState) {
            // TODO: level is sent but not used
            $processState->setComponentState('db', $state);
        });
        $db->on('schemaChange', function ($startupSchema, $dbSchema) {
            Logger::info(sprintf(
                "DB schema version changed. Started with %d, DB has %d. Restarting.",
                $startupSchema,
                $dbSchema
            ));
            $this->reload();
        });

        $db->setConfigWatch(
            $dbResourceName
            ? DbResourceConfigWatch::name($dbResourceName)
            : DbResourceConfigWatch::module('director')
        );

        return $db;
    }

    protected function registerSignalHandlers(LoopInterface $loop)
    {
        $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        };
        $funcReload = function () {
            $this->reload();
        };
        $loop->addSignal(SIGHUP, $funcReload);
        $loop->addSignal(SIGINT, $func);
        $loop->addSignal(SIGTERM, $func);
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        $this->loop->removeSignal($signal, $func);
        $this->shutdown();
    }

    public function reload()
    {
        if ($this->reloading) {
            Logger::error('Ignoring reload request, reload is already in progress');
            return;
        }
        $this->reloading = true;
        Logger::info('Going gown for reload now');
        $this->setState('reloading the main process');
        $this->daemonDb->disconnect()->then(function () {
            Process::restart();
        });
    }

    protected function shutdown()
    {
        if ($this->shuttingDown) {
            Logger::error('Ignoring shutdown request, shutdown is already in progress');
            return;
        }
        Logger::info('Shutting down');
        $this->shuttingDown = true;
        $this->setState('shutting down');
        $this->daemonDb->disconnect()->then(function () {
            Logger::info('DB has been disconnected, shutdown finished');
            $this->loop->stop();
        });
    }

    protected function setState($state)
    {
        if ($this->processState) {
            $this->processState->setState($state);
        }

        return $this;
    }
}
