<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\Objects;

use Icinga\Cli\Params;
use Icinga\Cli\Screen;
use Icinga\Module\Director\Clicommands\HostsCommand;
use Icinga\Module\Director\Db;

/**
 * Test adapter that bypasses CLI bootstrap for HostsCommand.
 *
 * Injects DB and params directly into the protected properties that
 * the CLI base constructor normally sets from the running application.
 * The object type is set explicitly because the class name derives it.
 */
class TestableHostsCommand extends HostsCommand
{
    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->type = 'host';
        $this->params = new Params(['program']);
        $this->isVerbose = false;
        $this->isDebugging = false;
        $this->screen = Screen::instance(STDOUT);
    }
}
