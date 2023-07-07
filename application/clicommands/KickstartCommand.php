<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\KickstartHelper;

/**
 * Kickstart a Director installation
 *
 * Once you prepared your DB resource this command retrieves information about
 * unapplied database migration and helps applying them.
 */
class KickstartCommand extends Command
{
    /**
     * Check whether a kickstart run is required
     *
     * This is the case when there is a kickstart.ini in your Directors config
     * directory and no ApiUser in your Director DB.
     *
     * This is mostly for automation, so one could create a Puppet manifest
     * as follows:
     *
     *     exec { 'Icinga Director Kickstart':
     *        path    => '/usr/local/bin:/usr/bin:/bin',
     *        command => 'icingacli director kickstart run',
     *        onlyif  => 'icingacli director kickstart required',
     *        require => Exec['Icinga Director DB migration'],
     *     }
     *
     * Exit code 0: A kickstart run is required.
     * Exit code 1: Kickstart is configured but a run is not required.
     * Exit code 2: A kickstart run is not required.
     */
    public function requiredAction()
    {
        if ($this->kickstart()->isConfigured()) {
            if ($this->kickstart()->isRequired()) {
                if ($this->isVerbose) {
                    echo "Kickstart has been configured and should be triggered\n";
                }

                exit(0);
            } else {
                echo "Kickstart configured, execution is not required\n";
                exit(1);
            }
        } else {
            echo "Kickstart has not been configured\n";
            exit(2);
        }
    }

    /**
     * Trigger the kickstart helper
     *
     * This will connect to the endpoint configured in your kickstart.ini,
     * store the given API user and import existing objects like zones,
     * endpoints and commands.
     *
     * /etc/icingaweb2/modules/director/kickstart.ini could look as follows:
     *
     *    [config]
     *    endpoint = "master-node.example.com"
     *
     *    ; Host can be an IP address or a hostname. Equals to endpoint name
     *    ; if not set:
     *    host = "127.0.0.1"
     *
     *    ; Port is 5665 if none given
     *    port = 5665
     *
     *    username = "director"
     *    password = "***"
     *
     */
    public function runAction()
    {
        $this->raiseLimits();
        $this->kickstart()->loadConfigFromFile()->run();
        exit(0);
    }

    protected function kickstart()
    {
        return new KickstartHelper($this->db());
    }
}
