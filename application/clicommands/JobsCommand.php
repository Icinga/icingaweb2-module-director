<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Job\JobRunner;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Application\Logger;
use Exception;

class JobsCommand extends Command
{
    public function runAction()
    {
        $forever = $this->params->shift('forever');
        if (! $forever && $this->params->getStandalone() === 'forever') {
            $forever = true;
            $this->params->shift();
        }

        $jobId = $this->params->shift();
        if ($jobId) {
            $this->raiseLimits();
            $job = DirectorJob::loadWithAutoIncId($jobId, $this->db());
            $job->run();
            exit(0);
        }

        if ($forever) {
            $this->runforever();
        } else {
            $this->runAllPendingJobs();
        }
    }

    protected function runforever()
    {
        // We'll terminate ourselves after 24h for now:
        $runUnless = time() + 86400;

        // We'll exit in case more than 100MB of memory are still in use
        // after the last job execution:
        $maxMem = 100 * 1024 * 1024;

        while (true) {
            $this->runAllPendingJobs();
            if (memory_get_usage() > $maxMem) {
                exit(0);
            }

            if (time() > $runUnless) {
                exit(0);
            }

            sleep(2);
        }
    }

    protected function runAllPendingJobs()
    {
        $jobs = new JobRunner($this->db());

        try {
            if ($this->hasBeenDisabled()) {
                return;
            }

            $jobs->runPendingJobs();
        } catch (Exception $e) {
            Logger::error('Director Job Error: ' . $e->getMessage());
            sleep(10);
        }
    }

    protected function hasBeenDisabled()
    {
        return $this->db()->settings()->disable_all_jobs === 'y';
    }
}
