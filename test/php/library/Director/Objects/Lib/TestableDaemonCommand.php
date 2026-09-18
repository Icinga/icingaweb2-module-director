<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects\Lib;

use Icinga\Cli\Params;
use Icinga\Cli\Screen;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Db;

/**
 * Test adapter that bypasses CLI bootstrap for DaemonCommand.
 *
 * Injects DB and params directly, and stubs out the four startup steps
 * so tests can check which ones ran without touching kickstart, import,
 * sync or deploy for real.
 */
class TestableDaemonCommand extends DaemonCommand
{
    /** @var string[] */
    public $stepsRun = [];

    public function __construct(Db $db, array $argv = [])
    {
        $this->db = $db;
        $this->params = new Params(array_merge(['program'], $argv));
        $this->isVerbose = in_array('--verbose', $argv);
        $this->isDebugging = false;
        $this->screen = Screen::instance(STDOUT);
    }

    public function runSetupStep(): void
    {
        $this->runSetup($this->params->get('db-resource'));
    }

    public function wantsSetupStep(): bool
    {
        return $this->wantsSetup();
    }

    protected function runKickstart(Db $db, bool $force = false): void
    {
        $this->stepsRun[] = 'kickstart';
    }

    protected function runImportAndSync(Db $db): void
    {
        $this->stepsRun[] = 'run-sync';
    }

    protected function deployConfig(Db $db): void
    {
        $this->stepsRun[] = 'deploy';
    }

    protected function restoreBasket(Db $db, string $path): void
    {
        $this->stepsRun[] = 'import:' . $path;
    }
}
