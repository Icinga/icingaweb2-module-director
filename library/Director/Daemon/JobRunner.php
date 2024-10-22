<?php

namespace Icinga\Module\Director\Daemon;

use gipfl\IcingaCliDaemon\FinishedProcessState;
use gipfl\IcingaCliDaemon\IcingaCliRpc;
use Icinga\Application\Logger;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorJob;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;

use function React\Promise\resolve;

class JobRunner implements DbBasedComponent
{
    /** @var Db */
    protected $db;

    /** @var LoopInterface */
    protected $loop;

    /** @var int[] */
    protected $scheduledIds = [];

    /** @var Promise[] */
    protected $runningIds = [];

    protected $checkInterval = 10;

    /** @var \React\EventLoop\TimerInterface */
    protected $timer;

    /** @var LogProxy */
    protected $logProxy;

    /** @var ProcessList */
    protected $running;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->running = new ProcessList($loop);
    }

    public function forwardLog(LogProxy $logProxy)
    {
        $this->logProxy = $logProxy;

        return $this;
    }

    /**
     * @param Db $db
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $db)
    {
        $this->db = $db;
        $check = function () {
            try {
                $this->checkForPendingJobs();
                $this->runNextPendingJob();
            } catch (\Exception $e) {
                Logger::error($e->getMessage());
            }
        };
        if ($this->timer === null) {
            $this->loop->futureTick($check);
        }
        if ($this->timer !== null) {
            Logger::info('Cancelling former timer');
            $this->loop->cancelTimer($this->timer);
        }
        $this->timer = $this->loop->addPeriodicTimer($this->checkInterval, $check);

        return resolve();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->scheduledIds = [];
        if ($this->timer !== null) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        $allFinished = $this->running->killOrTerminate();
        foreach ($this->runningIds as $id => $promise) {
            $promise->cancel();
        }
        $this->runningIds = [];

        return $allFinished;
    }

    protected function hasBeenDisabled()
    {
        $db = $this->db->getDbAdapter();
        return $db->fetchOne(
            $db->select()
                ->from('director_setting', 'setting_value')
                ->where('setting_name = ?', 'disable_all_jobs')
        ) === 'y';
    }

    protected function checkForPendingJobs()
    {
        if ($this->hasBeenDisabled()) {
            $this->scheduledIds = [];
            // TODO: disable jobs currently going on?
            return;
        }
        if (empty($this->scheduledIds)) {
            $this->loadNextIds();
        }
    }

    protected function runNextPendingJob()
    {
        if ($this->timer === null) {
            // Reset happened. Stopping?
            return;
        }

        if (! empty($this->runningIds)) {
            return;
        }
        while (! empty($this->scheduledIds)) {
            if ($this->runNextJob()) {
                break;
            }
        }
    }

    protected function loadNextIds()
    {
        $db = $this->db->getDbAdapter();

        foreach (
            $db->fetchCol(
                $db->select()->from('director_job', 'id')->where('disabled = ?', 'n')
            ) as $id
        ) {
            $this->scheduledIds[] = (int) $id;
        };
    }

    /**
     * @return bool
     */
    protected function runNextJob()
    {
        $id = \array_shift($this->scheduledIds);
        try {
            $job = DirectorJob::loadWithAutoIncId((int) $id, $this->db);
            if ($job->shouldRun()) {
                $this->runJob($job);
                return true;
            }
        } catch (\Exception $e) {
            Logger::error('Trying to schedule Job failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * @param DirectorJob $job
     */
    protected function runJob(DirectorJob $job)
    {
        $id = $job->get('id');
        $jobName = $job->get('job_name');
        Logger::debug("Job ($jobName) starting");
        $arguments = [
            'director',
            'job',
            'run',
            '--id',
            $job->get('id'),
            '--debug',
            '--rpc'
        ];
        $cli = new IcingaCliRpc();
        $cli->setArguments($arguments);
        $cli->on('start', function (Process $process) {
            $this->onProcessStarted($process);
        });

        // Happens on protocol (Netstring) errors or similar:
        $cli->on('error', function (\Exception $e) {
            Logger::error('UNEXPECTED: ' . rtrim($e->getMessage()));
        });
        if ($this->logProxy) {
            $logger = clone($this->logProxy);
            $logger->setPrefix("Job ($jobName): ");
            $cli->rpc()->setHandler($logger, 'logger');
        }
        unset($this->scheduledIds[$id]);
        $this->runningIds[$id] = $cli->run($this->loop)->then(function () use ($id, $jobName) {
            Logger::debug("Job ($jobName) finished");
        })->otherwise(function (\Exception $e) use ($id, $jobName) {
            Logger::error("Job ($jobName) failed: " . $e->getMessage());
        })->otherwise(function (FinishedProcessState $state) use ($jobName) {
            Logger::error("Job ($jobName) failed: " . $state->getReason());
        })->always(function () use ($id) {
            unset($this->runningIds[$id]);
            $this->loop->futureTick(function () {
                $this->runNextPendingJob();
            });
        });
    }

    /**
     * @return ProcessList
     */
    public function getProcessList()
    {
        return $this->running;
    }

    protected function onProcessStarted(Process $process)
    {
        $this->running->attach($process);
    }

    public function __destruct()
    {
        $this->stopDb();
        $this->logProxy = null;
        $this->loop = null;
    }
}
