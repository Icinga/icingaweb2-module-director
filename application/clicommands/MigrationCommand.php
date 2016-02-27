<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Db\Migrations;

/**
 * Handle DB migrations
 *
 * This command retrieves information about unapplied database migration and
 * helps applying them.
 */
class MigrationCommand extends Command
{
    /**
     * Check whether there are pending migrations
     *
     * This is mostly for automation, so one could create a Puppet manifest
     * as follows:
     *
     *     exec { 'Icinga Director DB migration':
     *       command => 'icingacli director migration run',
     *       onlyif  => 'icingacli director migration pending',
     *     }
     *
     * Exit code 0 means that there are pending migrations, code 1 that there
     * are no such. Use --verbose for human readable output
     */
    public function pendingAction()
    {
        if ($count = $this->migrations()->countPendingMigrations()) {
            if ($this->isVerbose) {
                if ($count === 1) {
                    echo "There is 1 pending migration\n";
                } else {
                    printf("There are %d pending migrations\n", $count);
                }
            }

            exit(0);
        } else {
            if ($this->isVerbose) {
                echo "There are no pending migrations\n";
            }

            exit(1);
        }
    }

    /**
     * Run any pending migrations
     *
     * All pending migrations will be silently applied
     */
    public function runAction()
    {
        $this->migrations()->applyPendingMigrations();
        exit(0);
    }

    protected function migrations()
    {
        return new Migrations($this->db());
    }
}
