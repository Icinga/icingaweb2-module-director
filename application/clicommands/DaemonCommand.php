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

        // import = "/etc/icingaweb2/modules/director/<basket>.json"
        $import = $this->params->get('import');
        $kickstartTriggered = false;
        if (! $kickstart->isConfigured()) {
            echo "Kickstart has not been configured\n";
            exit(1);
        }

        if (! $kickstart->isRequired()) {
            echo "Kickstart configured, execution is not required\n";
            $kickstartTriggered = true;
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
        if ($this->isVerbose && ! $kickstartTriggered) {
            echo "Kickstart has been configured and will be triggered\n";
        }

        if (! $kickstartTriggered && ! $kickstart->isRequired()) {
            // Like icingacli director kickstart run
            $this->raiseLimits();
            $kickstart->loadConfigFromFile()->run();
            $kickstartTriggered = true;
        }

        if (! $kickstartTriggered) {
            exit(1);
        }

        if ($import) {
            // import the basket from the given path
            $json = file_get_contents($import);
            BasketSnapshot::restoreJson($json, $this->db());
        }

        $runSync = $this->params->get('run-sync');
        if ($runSync) {
            $sources = ImportSource::loadAll($this->db());
            if (empty($sources)) {
                print "No import sources have been configured\n";
            } else {
                foreach ($sources as $source) {
                    if ($source->runImport()) {
                        print "New data has been imported\n";
                    } else {
                        print "Nothing has been changed, imported data is still up to date\n";
                    }
                }
            }

            $rules = SyncRule::loadAll($this->db());
            if (empty($rules)) {
                print "No sync rules have been configured\n";
            }  else {
                foreach ($rules as $rule) {
                    if ($rule->checkForChanges(true)) {
                        print "New data has been applied\n";
                    } else {
                        print "Nothing has been changed, synced data is still up to date\n";
                    }
                }
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
