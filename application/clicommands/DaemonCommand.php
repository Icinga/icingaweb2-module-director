<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Daemon\BackgroundDaemon;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Deployment\ConditionalDeployment;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\KickstartHelper;

class DaemonCommand extends Command
{
    /**
     * Run the main Director daemon
     *
     * USAGE
     *
     * icingacli director daemon run [--db-resource <name>] [--kickstart]
     *
     * OPTIONS
     *
     *   --kickstart  Run migrations, kickstart (if required) and deploy
     *                config before starting the daemon
     */
    public function runAction(): void
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $dbResource = $this->params->get('db-resource');
        if ($this->params->get('kickstart')) {
            $this->runKickstart($dbResource);
        }

        $daemon = new BackgroundDaemon();
        if ($dbResource) {
            $daemon->setDbResourceName($dbResource);
        }

        $daemon->run();
    }

    protected function runKickstart(?string $dbResource): void
    {
        $db = $dbResource === null ? $this->db() : Db::fromResourceName($dbResource);

        // Like icingacli director migration run
        (new Migrations($db))->applyPendingMigrations();

        // Like icingacli director kickstart required
        $kickstart = new KickstartHelper($db);
        if (! $kickstart->isConfigured()) {
            echo "Kickstart has not been configured\n";
            exit(1);
        }

        if (! $kickstart->isRequired()) {
            if ($this->isVerbose) {
                echo "Kickstart configured, execution is not required\n";
            }

            return;
        }

        if ($this->isVerbose) {
            echo "Kickstart has been configured and will be triggered\n";
        }

        // Like icingacli director kickstart run
        $this->raiseLimits();
        $kickstart->loadConfigFromFile()->run();

        // Like icingacli director config deploy
        $config = IcingaConfig::generate($db);
        $checksum = $config->getHexChecksum();
        $deployer = new ConditionalDeployment($db, $db->getDeploymentEndpoint()->api());
        if ($deployer->deploy($config)) {
            if ($this->isVerbose) {
                printf("Config '%s' has been deployed\n", $checksum);
            }
        } elseif ($this->isVerbose) {
            echo $deployer->getNoDeploymentReason() . "\n";
        }
    }
}
