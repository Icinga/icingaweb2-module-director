<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\Objects;

use Icinga\Cli\Params;
use Icinga\Cli\Screen;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Db;
use RuntimeException;
use Throwable;

/**
 * Test adapter that bypasses CLI bootstrap for DaemonCommand
 *
 * Injects DB and params directly, and stubs out the four startup steps
 * so tests can check which ones ran without touching kickstart, import,
 * sync or deploy for real. Failing the command throws instead of exiting,
 * and waiting between connection attempts returns immediately.
 */
class TestableDaemonCommand extends DaemonCommand
{
    /** @var string[] Steps that ran, in the order they ran */
    public $stepsRun = [];

    /** @var int[] Seconds the command wanted to wait for, one entry per wait */
    public $waitedFor = [];

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

    /**
     * @template T
     *
     * @param string $what Remote side, as it should read in log and error messages
     * @param callable(): T $connect Callback that establishes the connection
     * @param callable(Throwable): bool $isRetryable Check whether an error is worth retrying
     *
     * @return T
     */
    public function retryConnectionStep(string $what, callable $connect, callable $isRetryable)
    {
        return $this->retryConnection($what, $connect, $isRetryable);
    }

    protected function sleep(int $seconds): void
    {
        $this->waitedFor[] = $seconds;
    }

    protected function runKickstart(Db $db): void
    {
        $this->stepsRun[] = 'kickstart';
    }

    protected function runImportAndSync(Db $db): void
    {
        $this->stepsRun[] = 'run-automation';
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
