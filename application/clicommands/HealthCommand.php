<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\CheckPlugin\PluginState;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Health;

/**
 * Check Icinga Director Health
 *
 * Use this command as a CheckPlugin to monitor your Icinga Director health
 */
class HealthCommand extends Command
{
    /**
     * Run health checks
     *
     * Use this command to run all or a specific set of Health Checks.
     *
     * USAGE
     *
     * icingacli director health check [options]
     *
     * OPTIONS
     *
     *   --check <name>  Run only a specific set of checks
     *                   valid names: config, sync, import, job
     *   --db <name>     Use a specific Icinga Web DB resource
     */
    public function checkAction()
    {
        $health = new Health();
        if ($name = $this->params->get('db')) {
            $health->setDbResourceName($name);
        }

        if ($name = $this->params->get('check')) {
            $check = $health->getCheck($name);
            echo $check->getOutput();

            exit($check->getState()->getNumeric());
        } else {
            $state = new PluginState('OK');
            $checks = $health->getAllChecks();

            $output = [];
            foreach ($checks as $check) {
                $state->raise($check->getState());
                $output[] = $check->getOutput();
            }

            if ($state === 0) {
                echo "Icinga Director: everything is fine\n\n";
            } else {
                echo "Icinga Director: there are problems\n\n";
            }
            echo implode("\n", $output);
            exit($state->getNumeric());
        }
    }
}
