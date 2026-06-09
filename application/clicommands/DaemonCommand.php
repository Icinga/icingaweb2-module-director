<?php

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
     *
     * OPTIONS
     *
     *   --kickstart  Run migrations, kickstart (if required) and deploy
     *                config before starting the daemon. Retry on database
     *                connection error.
     *   --import     Path to the basket file to be imported
     *   --run-sync   Trigger import sources, and sync rules to sync data
     *   --deploy     Deploy config to Icinga
     */
    public function runAction()
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

    protected function runKickstart(?string $dbResource)
    {
        $dbCallback = $dbResource === null ? $this->db(...) : fn() => Db::fromResourceName($dbResource);

        $db = $this->retryDbConnection($dbCallback);
        Logger::info('Successfully connected to database');

        // Like icingacli director migration run
        (new Migrations($db))->applyPendingMigrations();

        // Like icingacli director kickstart required
        $kickstart = new KickstartHelper($db);

        if (! $kickstart->isConfigured()) {
            echo "Kickstart has not been configured\n";
            exit(1);
        }

        // import = "/etc/icingaweb2/modules/director/<basket>.json"
        $import = $this->params->get('import');
        if (! $kickstart->isRequired()) {
            echo "Kickstart configured, execution is not required\n";
            if ($import === null) {
                exit(1);
            }
        }

        if ($this->isVerbose) {
            echo "Kickstart has been configured and will be triggered\n";
        }

        // Like icingacli director kickstart run
        $this->raiseLimits();
        $kickstart->loadConfigFromFile()->run();

        if ($import) {
            // import the basket from the given path
            $json = file_get_contents($import);
            BasketSnapshot::restoreJson($json, $this->db());
        }

        if ($this->params->get('run-sync')) {
            $sources = ImportSource::loadAll($this->db());
            if (empty($sources)) {
                echo "No import sources have been configured\n";
            }
            foreach ($sources as $source) {
                echo $source->runImport()
                    ? "New data has been imported\n"
                    : "Nothing has been changed, imported data is still up to date\n";
            }

            $rules = SyncRule::loadAll($this->db());
            if (empty($rules)) {
                echo "No sync rules have been configured\n";
            }
            foreach ($rules as $rule) {
                echo $rule->applyChanges()
                    ? "New data has been applied\n"
                    : "Nothing has been changed, synced data is still up to date\n";
            }
        }

        $deploy = $this->params->get('deploy');
        if ($deploy) {
            // Like icingacli director config deploy
            $config = IcingaConfig::generate($db);
            $checksum = $config->getHexChecksum();
            $deployer = new ConditionalDeployment($db, $this->api());
            if ($deployer->deploy($config)) {
                if ($this->isVerbose) {
                    printf("Config '%s' has been deployed\n", $checksum);
                }
            } else {
                echo $deployer->getNoDeploymentReason() . "\n";
                exit(1);
            }
        }
    }

    /**
     * Retry to connect to the database
     *
     * Uses a five-second interval to retry for five minutes.
     *
     * @param callable $fn The callback to retry
     *
     * @return Db
     */
    protected function retryDbConnection(callable $fn): Db
    {
        $try = 0;
        while (true) {
            try {
                return $fn();
            // Zend_Db catches PDOException internally and re-throws it as
            // Zend_Db_Adapter_Exception, so catching PDOException alone
            // misses connection failures surfaced through Zend_Db.
            } catch (PDOException | Zend_Db_Adapter_Exception $e) {
                if (++$try > 60) {
                    $this->fail('Could not connect to database, stopped retrying after 5m: ' . $e->getMessage());
                }
                Logger::warning('Could not connect to database, retrying in 5s: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}
