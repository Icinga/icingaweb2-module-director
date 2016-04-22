<?php

namespace Icinga\Module\Director\Job;

use Icinga\Module\Director\Db;

class JobRunner
{
    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function runPendingJobs()
    {
        foreach ($this->getConfiguredJobs() as $job) {
            if ($job->isPending()) {
                $this->run($job);
            }
        }
    }

    protected function run(Job $job)
    {
        if ($this->shouldFork()) {
            $this->fork($job);
        } else {
            $job->run();
        }
    }

    protected function fork(Job $job)
    {
        $cmd = 'icingacli director job run ' . $job->id;
        $output = `$cmd`;
    }

    protected function shouldFork()
    {
        return true;
    }

    protected function getRegisteredJobs()
    {
    }
}
