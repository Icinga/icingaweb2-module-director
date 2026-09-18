<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Logger;
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
use PDOException;
use Zend_Db_Adapter_Exception;

class DaemonCommand extends Command
{
    /**
     * Run the main Director daemon
     *
     * USAGE
     *
     * icingacli director daemon run [--db-resource <name>] [--kickstart]
     *                                [--import <path>] [--run-sync] [--deploy]
     *
     * OPTIONS
     *
     *   --kickstart  Run kickstart if configured and required, before
     *                starting the daemon. Refuses to touch a DB that
     *                already has Endpoint, Zone or Command objects, run
     *                'icingacli director kickstart run' separately to
     *                recover an existing installation instead. Fails if
     *                kickstart isn't configured at all
     *   --import <path>    Restore a basket snapshot from the given file
     *   --run-sync         Run all import sources and sync rules
     *   --deploy           Deploy the generated config
     *
     * Each of these runs independently, you can pass any combination of
     * them (or just one) without the others. All of them apply pending
     * migrations first and retry the DB connection if it's not reachable
     * yet.
     *
     * A kickstart import is rolled back if it fails partway through. If it
     * succeeds but the daemon is interrupted before the config is deployed,
     * a later --deploy (even without --kickstart) still deploys it, even if
     * the generated config looks unchanged.
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
        foreach (['kickstart', 'import', 'run-sync', 'deploy'] as $flag) {
            if ($this->params->get($flag)) {
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
        $dbCallback = $dbResource === null ? $this->db(...) : fn () => Db::fromResourceName($dbResource);
        $db = $this->retryDbConnection($dbCallback);
        Logger::info('Successfully connected to database');

        // Like icingacli director migration run
        (new Migrations($db))->applyPendingMigrations();

        if ($this->params->get('kickstart')) {
            $this->runKickstart($db);
        }

        // import = "/etc/icingaweb2/modules/director/<basket>.json"
        $import = $this->params->get('import');
        if ($import) {
            $this->restoreBasket($db, $import);
        }

        if ($this->params->get('run-sync')) {
            $this->runImportAndSync($db);
        }

        if ($this->params->get('deploy')) {
            $this->deployConfig($db);
        }
    }

    /**
     * Restore a basket snapshot from a file
     *
     * @param Db $db Database connection to use
     * @param string $path Path to the basket snapshot file
     *
     * @return void
     */
    protected function restoreBasket(Db $db, string $path): void
    {
        BasketSnapshot::restoreJson(file_get_contents($path), $db);
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
            echo "Kickstart has not been configured\n";
            exit(1);
        }

        if ($kickstart->isRequired()) {
            if ($this->hasExistingKickstartObjects($db)) {
                echo "Refusing to kickstart, this DB already has Endpoint, Zone or Command objects.\n"
                    . "Run 'icingacli director kickstart run' separately to recover this installation.\n";
                exit(1);
            }

            if ($this->isVerbose) {
                echo "Kickstart has been configured and will be triggered\n";
            }

            // Persist before the import so a restart cannot lose the pending deployment.
            $db->settings()->set('initial_deployment_pending', 'y');

            // Like icingacli director kickstart run
            $this->raiseLimits();
            $kickstart->loadConfigFromFile()->run();
        } elseif ($this->isVerbose) {
            echo "Kickstart configured, execution is not required\n";
        }
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
        $sources = ImportSource::loadAll($db);
        if (empty($sources)) {
            echo "No import sources have been configured\n";
        }

        foreach ($sources as $source) {
            if ($source->runImport()) {
                echo "New data has been imported\n";
            } elseif ($source->get('import_state') === 'failing') {
                $this->fail(
                    "Import '%s' failed: %s",
                    $source->get('source_name'),
                    $source->get('last_error_message')
                );
            } else {
                echo "Nothing has been changed, imported data is still up to date\n";
            }
        }

        $rules = SyncRule::loadAll($db);
        if (empty($rules)) {
            echo "No sync rules have been configured\n";
        }

        foreach ($rules as $rule) {
            if ($rule->applyChanges()) {
                echo "New data has been applied\n";
            } elseif ($rule->get('sync_state') === 'failing') {
                $this->fail(
                    "Sync rule '%s' failed: %s",
                    $rule->get('rule_name'),
                    $rule->get('last_error_message')
                );
            } else {
                echo "Nothing has been changed, synced data is still up to date\n";
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
        $deployer = new ConditionalDeployment($db, $db->getDeploymentEndpoint()->api());
        if ($pending) {
            // A matching deployment log may belong to another package.
            $deployer->force();
        }

        if ($deployer->deploy($config)) {
            if ($this->isVerbose) {
                printf("Config '%s' has been deployed\n", $checksum);
            }

            $settings->set('initial_deployment_pending', null);
        } elseif ($this->isVerbose) {
            echo $deployer->getNoDeploymentReason() . "\n";
        }
    }

    /**
     * Retry connecting to the database until it's reachable
     *
     * Retries every 5 seconds for up to 5 minutes before giving up
     *
     * @param callable $fn Callback that opens the DB connection
     *
     * @return Db
     */
    protected function retryDbConnection(callable $fn): Db
    {
        $try = 0;
        while (true) {
            try {
                return $fn();
            } catch (PDOException | Zend_Db_Adapter_Exception $e) {
                // Zend_Db wraps every PDOException and rethrows it as
                // Zend_Db_Adapter_Exception, so we have to catch both
                if (++$try > 60) {
                    $this->fail('Could not connect to database, stopped retrying after 5m: ' . $e->getMessage());
                }

                Logger::warning('Could not connect to database, retrying in 5s: ' . $e->getMessage());
                sleep(5);
            }
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
