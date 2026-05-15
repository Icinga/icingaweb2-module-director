<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\Objects\TestableDaemonCommand;

class DaemonCommandTest extends BaseTestCase
{
    public function testWantsSetupIsFalseWithoutAnyStartupFlag(): void
    {
        $cmd = new TestableDaemonCommand();

        $this->assertFalse($cmd->wantsSetupStep());
    }

    public function testWantsSetupIsTrueForEachStartupFlagOnItsOwn(): void
    {
        foreach (['--kickstart', '--deploy'] as $flag) {
            $cmd = new TestableDaemonCommand(null, [$flag]);
            $this->assertTrue($cmd->wantsSetupStep(), "$flag alone must trigger setup");
        }
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

    public function testKickstartRunsWithoutTheDeployFlag(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--kickstart']);
        $cmd->runSetupStep();

        $this->assertEquals(['kickstart'], $cmd->stepsRun);
    }

    public function testBothStepsRunTogetherInOrder(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $cmd = new TestableDaemonCommand($this->getDb(), ['--kickstart', '--deploy']);
        $cmd->runSetupStep();

        $this->assertEquals(
            ['kickstart', 'deploy'],
            $cmd->stepsRun,
            'kickstart must still run before the deployment it makes pending'
        );
    }
}
