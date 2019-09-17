<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Daemon\BackgroundDaemon;

class DaemonCommand extends Command
{
    /**
     * Run the main Director daemon
     *
     * USAGE
     *
     * icingacli director daemon run [--db-resource <name>]
     */
    public function runAction()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $daemon = new BackgroundDaemon();
        if ($dbResource = $this->params->get('db-resource')) {
            $daemon->setDbResourceName($dbResource);
        }
        $daemon->run();
    }
}
