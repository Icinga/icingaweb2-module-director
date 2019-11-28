<?php

namespace Icinga\Module\Director\Daemon;

use gipfl\LinuxHealth\Memory;
use Icinga\Application\Logger;
use ipl\Stdlib\EventEmitter;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;

class ProcessList
{
    use EventEmitter;

    /** @var LoopInterface */
    protected $loop;

    /** @var \SplObjectStorage */
    protected $processes;

    /**
     * ProcessList constructor.
     * @param LoopInterface $loop
     * @param Process[] $processes
     */
    public function __construct(LoopInterface $loop, array $processes = [])
    {
        $this->loop = $loop;
        $this->processes = new \SplObjectStorage();
        foreach ($processes as $process) {
            $this->attach($process);
        }
    }

    public function attach(Process $process)
    {
        $this->processes->attach($process);
        $this->emit('start', [$process]);
        $process->on('exit', function () use ($process) {
            $this->detach($process);
            $this->emit('exit', [$process]);
        });

        return $this;
    }

    public function detach(Process $process)
    {
        $this->processes->detach($process);

        return $this;
    }

    /**
     * @param int $timeout
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function killOrTerminate($timeout = 5)
    {
        if ($this->processes->count() === 0) {
            return new FulfilledPromise();
        }
        $deferred = new Deferred();
        $killTimer = $this->loop->addTimer($timeout, function () use ($deferred) {
            /** @var Process $process */
            foreach ($this->processes as $process) {
                $pid = $process->getPid();
                Logger::error("Process $pid is still running, sending SIGKILL");
                $process->terminate(SIGKILL);
            }

            // Let's a little bit of delay after KILLing
            $this->loop->addTimer(0.1, function () use ($deferred) {
                $deferred->resolve();
            });
        });

        $timer = $this->loop->addPeriodicTimer($timeout / 20, function () use (
            $deferred,
            &$timer,
            $killTimer
        ) {
            $stopped = [];
            /** @var Process $process */
            foreach ($this->processes as $process) {
                if (! $process->isRunning()) {
                    $stopped[] = $process;
                }
            }
            foreach ($stopped as $process) {
                $this->processes->detach($process);
            }
            if ($this->processes->count() === 0) {
                $this->loop->cancelTimer($timer);
                $this->loop->cancelTimer($killTimer);
                $deferred->resolve();
            }
        });
        /** @var Process $process */
        foreach ($this->processes as $process) {
            $process->terminate(SIGTERM);
        }

        return $deferred->promise();
    }

    public function getOverview()
    {
        $info = [];

        /** @var Process $process */
        foreach ($this->processes as $process) {
            $pid = $process->getPid();
            $info[$pid] = (object) [
                'command' => preg_replace('/^exec /', '', $process->getCommand()),
                'running' => $process->isRunning(),
                'memory'  => Memory::getUsageForPid($pid)
            ];
        }

        return  $info;
    }
}
