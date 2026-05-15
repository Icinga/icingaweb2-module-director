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
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Throwable;

class DaemonCommand extends Command
{
    /**
     * Run the main Director daemon
     *
     * USAGE
     *
     * icingacli director daemon run [--db-resource <name>] [--kickstart]
     *                               [--import-basket <path>] [--run-automation]
     *                               [--deploy]
     *
     * OPTIONS
     *
     *   --kickstart             Run kickstart if configured and required,
     *                           before starting the daemon. Refuses to touch a
     *                           DB that already has Endpoint, Zone or Command
     *                           objects. Run 'icingacli director kickstart run'
     *                           separately to recover an existing installation
     *                           instead. Fails if kickstart isn't configured at
     *                           all
     *   --import-basket <path>  Restore a basket snapshot from the given file
     *   --run-automation        Run all import sources and sync rules
     *   --deploy                Deploy the generated config
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
        foreach (['kickstart', 'import-basket', 'run-automation', 'deploy'] as $flag) {
            if ($this->params->has($flag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Connect to the database and run the requested startup steps
     *
     * Validates basket option values and file readability before accessing the
     * database. Setup is not atomic. A failure stops startup, but changes
     * committed by earlier steps remain.
     *
     * @param ?string $dbResource DB resource to use, falls back to the configured default
     *
     * @return void
     */
    protected function runSetup(?string $dbResource): void
    {
        $basket = $this->getBasketSnapshotPath();

        $db = $dbResource === null ? $this->db() : Db::fromResourceName($dbResource);

        // Like icingacli director migration run
        (new Migrations($db))->applyPendingMigrations();

        if ($this->params->has('kickstart')) {
            $this->runKickstart($db);
        }

        if ($basket !== null) {
            $this->restoreBasket($db, $basket);
        }

        if ($this->params->has('run-automation')) {
            $this->runImportAndSync($db);
        }

        if ($this->params->has('deploy')) {
            $this->deployConfig($db);
        }
    }

    /**
     * Get the basket snapshot file passed with --import-basket
     *
     * Fails the command unless the option carries a readable path.
     *
     * @return ?string Null if --import-basket wasn't passed at all
     */
    protected function getBasketSnapshotPath(): ?string
    {
        $path = $this->params->get('import-basket');
        if ($path === null) {
            return null;
        }

        if (! is_string($path)) {
            $this->fail('--import-basket requires a file path');
        }

        if (! is_file($path) || ! is_readable($path)) {
            $this->fail('Cannot read basket snapshot "%s"', $path);
        }

        return $path;
    }

    /**
     * Restore a basket snapshot from a file
     *
     * @param Db $db Database connection to use
     * @param string $path Path to a readable basket snapshot file
     *
     * @return void
     */
    protected function restoreBasket(Db $db, string $path): void
    {
        $json = file_get_contents($path);
        if ($json === false) {
            $this->fail('Failed to read basket snapshot "%s"', $path);
        }

        try {
            $keptValuesCount = BasketSnapshot::restoreJson($json, $db);
        } catch (Throwable $e) {
            $this->fail('Failed to restore basket snapshot "%s": %s', $path, $e->getMessage());
        }

        Logger::info('Objects from basket snapshot "%s" have been restored', $path);
        if ($keptValuesCount > 0) {
            Logger::warning(
                '%d stored value(s) were kept under their old name or dropped in this'
                . ' basket, either a Data Field still owns the name, or a renamed'
                . " value's new key was already taken",
                $keptValuesCount
            );
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
     * Run all import sources and apply sync rules with pending changes
     *
     * @param Db $db Database connection to use
     *
     * @return void
     */
    protected function runImportAndSync(Db $db): void
    {
        Logger::info('Running all import sources and sync rules');

        $sources = ImportSource::loadAll($db);
        if (empty($sources)) {
            Logger::info('No import sources have been configured');
        }

        foreach ($sources as $source) {
            // runImport() reports changes before it stores them, so only the
            // import state tells whether the run as a whole succeeded.
            $hasChanges = $source->runImport();
            if ($source->get('import_state') === 'failing') {
                $this->fail(
                    "Import '%s' failed: %s",
                    $source->get('source_name'),
                    $source->get('last_error_message')
                );
            } elseif ($hasChanges) {
                Logger::info("Import '%s' provided new data", $source->get('source_name'));
            } else {
                Logger::info("Import '%s' is still up to date", $source->get('source_name'));
            }
        }

        $rules = SyncRule::loadAll($db);
        if (empty($rules)) {
            Logger::info('No sync rules have been configured');
        }

        foreach ($rules as $rule) {
            // As for import sources, check the state first. The return value
            // is false both for a failed run and for a run without changes.
            $hasChanges = $rule->applyChanges();
            if ($rule->get('sync_state') === 'failing') {
                $this->fail(
                    "Sync rule '%s' failed: %s",
                    $rule->get('rule_name'),
                    $rule->get('last_error_message')
                );
            } elseif ($hasChanges) {
                Logger::info("Sync rule '%s' applied new data", $rule->get('rule_name'));
            } else {
                Logger::info("Sync rule '%s' is still up to date", $rule->get('rule_name'));
            }
        }
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
