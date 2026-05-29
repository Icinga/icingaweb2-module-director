<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects\Lib;

use Icinga\Cli\Params;
use Icinga\Cli\Screen;
use Icinga\Module\Director\Clicommands\MigrateCommand;
use Icinga\Module\Director\Db;

/**
 * Test adapter that bypasses CLI bootstrap for MigrateCommand.
 *
 * Injects DB and params directly into the protected properties that
 * the CLI base constructor normally sets from the running application.
 */
class TestableMigrateCommand extends MigrateCommand
{
    public function __construct(Db $db, array $argv = [])
    {
        $this->db = $db;
        $this->params = new Params(array_merge(['program'], $argv));
        $this->isVerbose = in_array('--verbose', $argv);
        $this->isDebugging = false;
        $this->screen = Screen::instance(STDOUT);
    }

    public function runDatafields(): string
    {
        ob_start();
        $this->datafieldsAction();

        return (string) ob_get_clean();
    }
}
