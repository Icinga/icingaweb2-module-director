<?php

namespace Icinga\Module\Director\Clicommands;

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
        $db = $dbResource === null ? $this->db() : Db::fromResourceName($dbResource);

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
}
