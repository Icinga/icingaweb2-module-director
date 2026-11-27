<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Exception\MissingParameterException;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Lib\Objects\TestableSyncruleCommand;

class SyncruleCommandTest extends BaseTestCase
{
    /** @var string[] Names of the sync rules created by a single test */
    private $ruleNames = [];

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            foreach ($this->ruleNames as $name) {
                if (SyncRule::exists($name, $this->getDb())) {
                    SyncRule::load($name, $this->getDb())->delete();
                }
            }
        }

        $this->ruleNames = [];

        parent::tearDown();
    }

    public function testBareNameDoesNotDeleteTheRuleNamedOne(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createRule('1');

        $this->expectMissingName(['--name']);
        $this->assertTrue(SyncRule::exists('1', $this->getDb()));
    }

    public function testRepeatedNameIsRejected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createRule('linux-servers');

        $this->expectMissingName(['--name', 'linux-servers', '--name', 'linux-servers']);
        $this->assertTrue(SyncRule::exists('linux-servers', $this->getDb()));
    }

    public function testEmptyNameIsRejected(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->expectMissingName(['--name=']);
    }

    public function testNumericNamesCanBeDeleted(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        foreach (['0', '1'] as $name) {
            $this->createRule($name);

            ob_start();
            (new TestableSyncruleCommand($this->getDb(), ['--name', $name]))->deleteAction();
            ob_end_clean();

            $this->assertFalse(SyncRule::exists($name, $this->getDb()), "Rule '$name' must be deleted");
        }
    }

    /**
     * Run delete with the given arguments and make sure it bails out
     *
     * @param string[] $argv Command line arguments, without the program name
     */
    private function expectMissingName(array $argv): void
    {
        try {
            (new TestableSyncruleCommand($this->getDb(), $argv))->deleteAction();
            $this->fail('Delete must refuse ' . implode(' ', $argv));
        } catch (MissingParameterException $e) {
            $this->addToAssertionCount(1);
        }
    }

    private function createRule(string $name): void
    {
        SyncRule::create([
            'rule_name'      => $name,
            'object_type'    => 'host',
            'update_policy'  => 'merge',
            'purge_existing' => 'n'
        ])->store($this->getDb());

        $this->ruleNames[] = $name;
    }
}
