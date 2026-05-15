<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\Objects;

use Icinga\Cli\Params;
use Icinga\Cli\Screen;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Db;
use RuntimeException;

/**
 * Test adapter that bypasses CLI bootstrap for DaemonCommand
 *
 * Injects DB and params directly, and stubs out the startup steps so tests
 * can check which ones ran without kickstarting or deploying for real.
 * Failing the command throws instead of exiting.
 */
class TestableDaemonCommand extends DaemonCommand
{
    /** @var string[] Steps that ran, in the order they ran */
    public $stepsRun = [];

    /**
     * Create a new TestableDaemonCommand
     *
     * @param ?Db $db Database connection to inject, null for tests that must not reach the DB
     * @param string[] $argv Command line arguments, without the program name
     */
    public function __construct(?Db $db = null, array $argv = [])
    {
        $this->db = $db;
        $this->params = new Params(array_merge(['program'], $argv));
        $this->isVerbose = in_array('--verbose', $argv);
        $this->isDebugging = false;
        $this->screen = Screen::instance(STDOUT);
    }

    /**
     * Report a failure without ending the test run
     *
     * @param string $msg Message, optionally with sprintf placeholders for the remaining arguments
     *
     * @return never
     *
     * @throws RuntimeException Always, carrying the formatted message
     */
    public function fail($msg)
    {
        $args = func_get_args();
        array_shift($args);

        throw new RuntimeException(count($args) ? vsprintf($msg, $args) : $msg);
    }

    public function runSetupStep(): void
    {
        $this->runSetup($this->params->get('db-resource'));
    }

    public function wantsSetupStep(): bool
    {
        return $this->wantsSetup();
    }

    protected function runKickstart(Db $db): void
    {
        $this->stepsRun[] = 'kickstart';
    }

    protected function deployConfig(Db $db): void
    {
        $this->stepsRun[] = 'deploy';
    }
}
