<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\Objects;

use Icinga\Cli\Params;
use Icinga\Module\Director\Clicommands\SyncruleCommand;
use Icinga\Module\Director\Db;

/**
 * Test adapter that skips the CLI bootstrap for SyncruleCommand
 */
class TestableSyncruleCommand extends SyncruleCommand
{
    /**
     * Create a new TestableSyncruleCommand
     *
     * @param Db $db Database connection to use
     * @param string[] $argv Command line arguments, without the program name
     */
    public function __construct(Db $db, array $argv = [])
    {
        $this->db = $db;
        $this->params = new Params(array_merge(['program'], $argv));
    }
}
