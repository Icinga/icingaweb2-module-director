<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Job\JobRunner;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Import\Sync;
use Icinga\Application\Benchmark;
use Icinga\Application\Logger;
use Exception;

class JobsCommand extends Command
{
    public function runAction()
    {
        $job = $this->params->shift();
        if ($job) {
            echo "Running (theoretically) $job\n";
            return;
        }

        if ($this->params->shift('once')) {
            $this->runAllPendingJobs();
        } else {
            $this->runforever();
        }
    }

    protected function runforever()
    {
        while (true) {
            $this->runAllPendingJobs();
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
        return $this->db()->getSetting('disable_all_jobs') === 'y';
    }
}
