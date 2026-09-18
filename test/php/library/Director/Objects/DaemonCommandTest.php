<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Objects\Lib\TestableDaemonCommand;

class DaemonCommandTest extends BaseTestCase
{
    public function testWantsSetupIsFalseWithoutAnyStartupFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb());

        $this->assertFalse($cmd->wantsSetupStep());
    }

    public function testWantsSetupIsTrueForEachStartupFlagOnItsOwn(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        foreach (['--kickstart', '--import', '--run-sync', '--deploy'] as $flag) {
            $cmd = new TestableDaemonCommand($this->getDb(), [$flag]);
            $this->assertTrue($cmd->wantsSetupStep(), "$flag alone must trigger setup");
        }
    }

    public function testImportRunsWithoutTheKickstartFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--import', '/tmp/basket.json']);
        $cmd->runSetupStep();

        $this->assertEquals(['import:/tmp/basket.json'], $cmd->stepsRun);
    }

    public function testRunSyncRunsWithoutTheKickstartFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--run-sync']);
        $cmd->runSetupStep();

        $this->assertEquals(['run-sync'], $cmd->stepsRun);
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

        $cmd = new TestableDaemonCommand($this->getDb(), [
            '--kickstart',
            '--import',
            '/tmp/basket.json',
            '--run-sync',
            '--deploy',
        ]);
        $cmd->runSetupStep();

        $this->assertEquals(
            ['kickstart', 'import:/tmp/basket.json', 'run-sync', 'deploy'],
            $cmd->stepsRun,
            'the four steps must run in the same order they always have'
        );
    }
}
