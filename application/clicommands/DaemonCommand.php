<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Logger;
use Icinga\Exception\ConfigurationError;
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
     *                               [--deploy]
     *
     * OPTIONS
     *
     *   --kickstart  Run kickstart if configured and required, before starting
     *                the daemon. Refuses to touch a DB that already has
     *                Endpoint, Zone or Command objects. Run 'icingacli director
     *                kickstart run' separately to recover an existing
     *                installation instead. Fails if kickstart isn't configured
     *                at all
     *   --deploy     Deploy the generated config
     */
    public function runAction(): void
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $dbResource = $this->params->get('db-resource');
        if ($this->wantsSetup()) {
            $this->runSetup($dbResource);
        }

        $daemon = new BackgroundDaemon();
        if ($dbResource) {
            $daemon->setDbResourceName($dbResource);
        }

        $daemon->run();
    }

    /**
     * Check if any startup flag was passed
     *
     * @return bool
     */
    protected function wantsSetup(): bool
    {
        foreach (['kickstart', 'deploy'] as $flag) {
            if ($this->params->has($flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Connect to the database and run the requested startup steps
     *
     * @param ?string $dbResource DB resource to use, falls back to the configured default
     *
     * @return void
     */
    protected function runSetup(?string $dbResource): void
    {
        $db = $dbResource === null ? $this->db() : Db::fromResourceName($dbResource);

        // Like icingacli director migration run
        (new Migrations($db))->applyPendingMigrations();

        if ($this->params->has('kickstart')) {
            $this->runKickstart($db);
        }

        if ($this->params->has('deploy')) {
            $this->deployConfig($db);
        }
    }

    /**
     * Run kickstart if it's configured and required
     *
     * @param Db $db Database connection to use
     *
     * @return void
     */
    protected function runKickstart(Db $db): void
    {
        // Like icingacli director kickstart required
        $kickstart = new KickstartHelper($db);
        if (! $kickstart->isConfigured()) {
            $this->fail('Kickstart has not been configured');
        }

        if (! $kickstart->isRequired()) {
            Logger::info('Kickstart is configured, execution is not required');

            return;
        }

        if ($this->hasExistingKickstartObjects($db)) {
            $this->fail(
                "Refusing to kickstart, this DB already has Endpoint, Zone or Command objects.\n"
                . "Run 'icingacli director kickstart run' separately to recover this installation."
            );
        }

        Logger::info('Kickstart has been configured and will be triggered');

        // Persist before the import so a restart cannot lose the pending deployment.
        $db->settings()->set('initial_deployment_pending', 'y');

        // Like icingacli director kickstart run
        $this->raiseLimits();
        $kickstart->loadConfigFromFile()->run();
    }

    /**
     * Generate and deploy the current config
     *
     * @param Db $db Database connection to use
     *
     * @return void
     */
    protected function deployConfig(Db $db): void
    {
        $settings = $db->settings();
        $pending = $settings->get('initial_deployment_pending') === 'y';

        // Like icingacli director config deploy
        $config = IcingaConfig::generate($db);
        $checksum = $config->getHexChecksum();

        try {
            $endpoint = $db->getDeploymentEndpoint();
        } catch (ConfigurationError $e) {
            $this->fail('Cannot deploy, no deployment endpoint is configured yet: %s', $e->getMessage());
        }

        $deployer = new ConditionalDeployment($db, $endpoint->api());
        if ($pending) {
            // A matching deployment log may belong to another package.
            $deployer->force();
        }

        if ($deployer->deploy($config)) {
            Logger::info("Config '%s' has been deployed", $checksum);
            $settings->set('initial_deployment_pending', null);
        } else {
            Logger::info('Nothing has been deployed: %s', $deployer->getNoDeploymentReason());
        }
    }

    /**
     * Check if a kickstart run could still remove Endpoint, Zone or Command objects
     *
     * Only counts objects a previous kickstart imported, a kickstart run
     * never touches manually created ones like templates
     *
     * @param Db $db Database connection to check
     *
     * @return bool
     */
    protected function hasExistingKickstartObjects(Db $db): bool
    {
        $summary = $db->getObjectSummary();

        foreach (['endpoint', 'zone', 'command'] as $type) {
            if ((int) $summary[$type]->cnt_external > 0) {
                return true;
            }
        }

        return false;
    }
}
