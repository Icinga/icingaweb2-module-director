<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Import\Sync;
use Icinga\Application\Benchmark;
use Exception;

class JobsCommand extends Command
{
    public function runAction()
    {
        if ($this->hasBeenDisabled()) {
            return;
        }

        $this->runScheduledImports()
             ->runScheduledSyncs()
             ->syncCoreStages()
             ->runScheduledDeployments()
             ;
    }

    public function renderconfigAction()
    {
        IcingaConfig::generate($this->db());
    }

    protected function hasBeenDisabled()
    {
        return false;
    }

    protected function runScheduledImports()
    {
        foreach (ImportSource::loadAll($this->db()) as $source) {
            Benchmark::measure('Starting with import ' . $source->source_name);
            try {

                $import = new Import($source);
                if ($import->providesChanges()) {
                    printf('Import "%s" provides changes, triggering run... ', $source->source_name);
                    Benchmark::measure('Found changes for ' . $source->source_name);
                    if ($import->run()) {
                        Benchmark::measure('Import succeeded for ' . $source->source_name);
                        print "SUCCEEDED\n";
                    }
                }
            } catch (Exception $e) {
                echo $this->screen->colorize('ERROR: ' . $e->getMessage(), 'red') . "\n";
                Benchmark::measure('FAILED');
            }
        }

        return $this;
    }

    protected function runScheduledSyncs()
    {
        // TODO: import-triggered:
        //      foreach $rule->involvedImports() -> if changedsince -> ... syncChangedRows

        foreach (SyncRule::loadAll($this->db) as $rule) {
            Benchmark::measure('Checking sync rule ' . $rule->rule_name);
            $sync = new Sync($rule);
            if ($sync->hasModifications()) {
                printf('Sync rule "%s" provides changes, triggering sync... ', $rule->rule_name);
                Benchmark::measure('Got modifications for sync rule ' . $rule->rule_name);

                if ($sync->apply()) {
                    Benchmark::measure('Successfully synced rule ' . $rule->rule_name);
                    print "SUCCEEDED\n";
                }
            }
        }

        return $this;
    }

    protected function syncCoreStages()
    {
        return $this;
    }

    protected function runScheduledDeployments()
    {
        return $this;
    }
}
