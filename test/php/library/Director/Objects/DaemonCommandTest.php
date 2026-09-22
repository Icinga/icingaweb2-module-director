<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\Objects\TestableDaemonCommand;
use RuntimeException;
use Throwable;
use Zend_Db_Adapter_Exception;

class DaemonCommandTest extends BaseTestCase
{
    private const RESTORED_TEMPLATE = '___TEST___daemon-basket-template';

    /** @var string[] Basket snapshot files created for a single test */
    private $basketPaths = [];

    public function tearDown(): void
    {
        foreach ($this->basketPaths as $path) {
            unlink($path);
        }

        $this->basketPaths = [];

        if ($this->hasDb() && IcingaHost::exists(self::RESTORED_TEMPLATE, $this->getDb())) {
            IcingaHost::load(self::RESTORED_TEMPLATE, $this->getDb())->delete();
        }

        parent::tearDown();
    }

    public function testWantsSetupIsFalseWithoutAnyStartupFlag(): void
    {
        $cmd = new TestableDaemonCommand();

        $this->assertFalse($cmd->wantsSetupStep());
    }

    public function testWantsSetupIsTrueForEachStartupFlagOnItsOwn(): void
    {
        foreach (['--kickstart', '--import-basket', '--run-automation', '--deploy'] as $flag) {
            $cmd = new TestableDaemonCommand(null, [$flag]);
            $this->assertTrue($cmd->wantsSetupStep(), "$flag alone must trigger setup");
        }
    }

    public function testKickstartRunsWithoutTheOtherFlags(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--kickstart']);
        $cmd->runSetupStep();

        $this->assertEquals(['kickstart'], $cmd->stepsRun);
    }

    public function testImportRunsWithoutTheKickstartFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $path = $this->createBasketFile();
        $cmd = new TestableDaemonCommand($this->getDb(), ['--import-basket', $path]);
        $cmd->runSetupStep();

        $this->assertEquals(['import:' . $path], $cmd->stepsRun);
    }

    public function testEveryGivenBasketIsRestoredInOrder(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $first = $this->createBasketFile();
        $second = $this->createBasketFile();
        $cmd = new TestableDaemonCommand($this->getDb(), [
            '--import-basket',
            $first,
            '--import-basket',
            $second,
        ]);
        $cmd->runSetupStep();

        $this->assertEquals(['import:' . $first, 'import:' . $second], $cmd->stepsRun);
    }

    public function testRunAutomationRunsWithoutTheKickstartFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--run-automation']);
        $cmd->runSetupStep();

        $this->assertEquals(['run-automation'], $cmd->stepsRun);
    }

    public function testDeployRunsWithoutTheKickstartFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--deploy']);
        $cmd->runSetupStep();

        $this->assertEquals(['deploy'], $cmd->stepsRun);
    }

    public function testAllFourStepsRunTogetherInOrder(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $path = $this->createBasketFile();
        $cmd = new TestableDaemonCommand($this->getDb(), [
            '--kickstart',
            '--import-basket',
            $path,
            '--run-automation',
            '--deploy',
        ]);
        $cmd->runSetupStep();

        $this->assertEquals(
            ['kickstart', 'import:' . $path, 'run-automation', 'deploy'],
            $cmd->stepsRun,
            'the four steps must run in the same order they always have'
        );
    }

    /**
     * A rejected basket option must be spotted before the first DB write, or an
     * aborted setup leaves a half-provisioned DB behind that never kickstarts again
     */
    public function testInvalidBasketOptionStopsSetupBeforeAnyStepRuns(): void
    {
        $cases = [
            'no path at all' => [['--import-basket'], '--import-basket requires a file path'],
            'unreadable path' => [
                ['--import-basket', '/tmp/___TEST___does-not-exist.json'],
                'Cannot read basket snapshot "/tmp/___TEST___does-not-exist.json"'
            ],
            'one of several paths unreadable' => [
                ['--import-basket', $this->createBasketFile(), '--import-basket', '/tmp/___TEST___gone.json'],
                'Cannot read basket snapshot "/tmp/___TEST___gone.json"'
            ],
        ];

        foreach ($cases as $label => [$argv, $expectedMessage]) {
            // No DB is injected, so reaching any step would raise a different error.
            $cmd = new TestableDaemonCommand(null, array_merge(['--kickstart'], $argv));

            try {
                $cmd->runSetupStep();
                $this->fail("$label: the setup must not start with an unusable --import-basket");
            } catch (RuntimeException $e) {
                $this->assertSame($expectedMessage, $e->getMessage(), $label);
            }

            $this->assertSame([], $cmd->stepsRun, "$label: no step may run");
        }
    }

    public function testConnectionRetriesUntilItSucceeds(): void
    {
        $cmd = new TestableDaemonCommand();
        $attempts = 0;

        $connection = $cmd->retryConnectionStep(
            'database',
            function () use (&$attempts) {
                if (++$attempts < 3) {
                    throw new Zend_Db_Adapter_Exception('Connection refused');
                }

                return 'connected';
            },
            fn (Throwable $e) => $e instanceof Zend_Db_Adapter_Exception
        );

        $this->assertSame('connected', $connection);
        $this->assertSame(3, $attempts);
        $this->assertSame([5, 5], $cmd->waitedFor, 'every failed attempt must wait five seconds');
    }

    public function testOtherErrorsAreNotRetried(): void
    {
        $cmd = new TestableDaemonCommand();
        $attempts = 0;
        $caught = null;

        try {
            $cmd->retryConnectionStep(
                'database',
                function () use (&$attempts) {
                    $attempts++;

                    throw new ConfigurationError('Resource has not been configured');
                },
                fn (Throwable $e) => $e instanceof Zend_Db_Adapter_Exception
            );
        } catch (ConfigurationError $e) {
            $caught = $e;
        }

        $this->assertSame(
            'Resource has not been configured',
            $caught->getMessage(),
            'A misconfigured resource must fail immediately'
        );
        $this->assertSame(1, $attempts);
        $this->assertSame([], $cmd->waitedFor);
    }

    public function testConnectionGivesUpAfterFiveMinutes(): void
    {
        $cmd = new TestableDaemonCommand();
        $attempts = 0;
        $caught = null;

        try {
            $cmd->retryConnectionStep(
                'database',
                function () use (&$attempts) {
                    $attempts++;

                    throw new Zend_Db_Adapter_Exception('Connection refused');
                },
                fn (Throwable $e) => $e instanceof Zend_Db_Adapter_Exception
            );
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        $this->assertSame(
            'Could not connect to database, giving up after 60 attempts: Connection refused',
            $caught->getMessage(),
            'An unreachable database must not be retried forever'
        );
        $this->assertSame(60, $attempts);
        $this->assertCount(59, $cmd->waitedFor, 'there is no wait after the last attempt');
    }

    public function testRestoreBasketRestoresObjectsFromTheGivenFile(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $template = IcingaHost::create([
            'object_name' => self::RESTORED_TEMPLATE,
            'object_type' => 'template',
            'address'     => '127.0.0.1',
        ], $db);
        $template->store();
        $snapshot = json_encode([
            'HostTemplate' => [self::RESTORED_TEMPLATE => (new Exporter($db))->export($template)],
        ]);
        $template->delete();

        $path = tempnam(sys_get_temp_dir(), '___TEST___basket');
        file_put_contents($path, $snapshot);
        $this->basketPaths[] = $path;

        $command = $this->getMockBuilder(DaemonCommand::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fail'])
            ->getMock();
        $command->expects($this->never())->method('fail');

        self::callMethod($command, 'restoreBasket', [$db, $path]);

        $this->assertTrue(IcingaHost::exists(self::RESTORED_TEMPLATE, $db));
        $this->assertSame('127.0.0.1', IcingaHost::load(self::RESTORED_TEMPLATE, $db)->get('address'));
    }

    /**
     * @return string Path to an empty basket snapshot, removed again in tearDown
     */
    private function createBasketFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), '___TEST___basket');
        file_put_contents($path, "{}\n");
        $this->basketPaths[] = $path;

        return $path;
    }
}
