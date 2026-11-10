<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectsCommand;
use Icinga\Module\Director\CustomVariable\CustomPropertySources;
use Ramsey\Uuid\Uuid;

/**
 * Manage Icinga Hosts
 *
 * Use this command to list Icinga Host objects
 */
class HostsCommand extends ObjectsCommand
{
    /**
     * Relink existing custom variables to a matching Custom Property
     *
     * Scans every host in the database and links any variable that matches a
     * Custom Property the host reaches by name: one attached to the host, to
     * one of its templates, to its check command or to a command that the
     * check command imports. Variables already linked to a Custom Property are
     * left as they are, and no values are changed. Run this after adding a
     * Custom Property for variables hosts already had.
     *
     * USAGE
     *
     * icingacli director hosts refreshCustomVars
     *
     * @return void
     */
    public function refreshCustomVarsAction(): void
    {
        $hosts = $this->getObjects();
        $reachable = (new CustomPropertySources($this->db()))->getReachablePropertyUuids(
            'host',
            array_map(fn($host) => (int) $host->get('id'), $hosts)
        );

        foreach ($hosts as $host) {
            $vars = $host->vars();
            foreach ($reachable[(int) $host->get('id')] as $key => $uuid) {
                if ($vars->get($key)?->getUuid() === null) {
                    $vars->registerVarUuid($key, Uuid::fromBytes($uuid));
                }
            }

            $vars->storeToDb($host);
        }
    }
}
