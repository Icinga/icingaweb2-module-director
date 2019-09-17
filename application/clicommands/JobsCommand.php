<?php

namespace Icinga\Module\Director\Clicommands;

use Exception;
use gipfl\Cli\Process;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Daemon\JsonRpcLogWriter as JsonRpcLogWriterAlias;
use Icinga\Module\Director\Daemon\Logger;
use Icinga\Module\Director\Objects\DirectorJob;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class JobsCommand extends Command
{
    public function runAction()
    {
        $loop = Loop::create();
        if ($this->params->get('rpc')) {
            $this->enableRpc($loop);
        }
        if ($this->params->get('rpc') && $jobId = $this->params->get('id')) {
            $exitCode = 1;
            $jobId = (int) $jobId;
            $loop->futureTick(function () use ($jobId, $loop, &$exitCode) {
                Process::setTitle('icinga::director::job');
                try {
                    $this->raiseLimits();
                    $job = DirectorJob::loadWithAutoIncId($jobId, $this->db());
                    Process::setTitle('icinga::director::job (' . $job->get('job_name') . ')');
                    if ($job->run()) {
                        $exitCode = 0;
                    } else {
                        $exitCode = 1;
                    }
                } catch (Exception $e) {
                    Logger::error($e->getMessage());
                    $exitCode = 1;
                }
                $loop->futureTick(function () use ($loop) {
                    $loop->stop();
                });
            });
        } else {
            Logger::error('This command is no longer available. Please check our Upgrading documentation');
            $exitCode = 1;
        }

        $loop->run();
        exit($exitCode);
    }

    protected function enableRpc(LoopInterface $loop)
    {
        // stream_set_blocking(STDIN, 0);
        // stream_set_blocking(STDOUT, 0);
        // print_r(stream_get_meta_data(STDIN));
        // stream_set_write_buffer(STDOUT, 0);
        // ini_set('implicit_flush', 1);
        $netString = new StreamWrapper(
            new ReadableResourceStream(STDIN, $loop),
            new WritableResourceStream(STDOUT, $loop)
        );
        $jsonRpc = new Connection();
        $jsonRpc->handle($netString);

        Logger::replaceRunningInstance(new JsonRpcLogWriterAlias($jsonRpc));
    }
}
