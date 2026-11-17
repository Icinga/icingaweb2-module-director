<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Application\Config;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\ProvidedHook\Icingadb\CustomVarRenderer;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Icingadb\Model\Host;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class CheckCommandVarRendererTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function setUp(): void
    {
        parent::setUp();

        // The renderer connects through the module's db resource, point it at the test database
        if ($this->hasDb()) {
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testCheckCommandVariablesOfAHostAreLabeledAndMasked(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $token = $this->createFixtureProperty('token', 'sensitive');
        $mode = $this->createFixtureProperty('mode');
        $login = $this->createFixtureProperty('login', 'fixed-dictionary');
        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'parent_uuid' => $login->get('uuid'),
            'key_name'    => 'password',
            'value_type'  => 'sensitive',
            'label'       => 'Password',
        ], $this->getDb())->store();
        $this->createFixtureCommand('plugin', 'template', [], [$login]);
        $this->createFixtureCommand('check-api', 'object', ['plugin'], [$token, $mode]);
        $this->createFixtureHost('linux', 'template', [], 'check-api');
        $this->createFixtureHost('web01', 'object', ['linux']);

        $renderer = new CustomVarRenderer();

        $this->assertTrue($renderer->prefetchForObject(new Host(['name' => $this->fx('web01')])));
        $this->assertSame('***', $renderer->renderCustomVarValue($this->fx('token'), 's3cr3t'));
        $this->assertSame('***', $renderer->renderCustomVarValue('password', 's3cr3t', $this->fx('login')));
        $this->assertStringContainsString('>mode</span>', $renderer->renderCustomVarKey($this->fx('mode'))->render());
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }
}
