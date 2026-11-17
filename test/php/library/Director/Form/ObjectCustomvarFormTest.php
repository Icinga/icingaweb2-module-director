<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\ObjectCustomvarForm;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class ObjectCustomvarFormTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testTheDialogOffersInheritedVariablesButNotOwnOrAddedOnes(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $inherited = $this->createFixtureProperty('inherited');
        $fromCommand = $this->createFixtureProperty('from-command');
        $own = $this->createFixtureProperty('own');
        $added = $this->createFixtureProperty('added');
        $this->createFixtureCommand('check-disk', 'object', [], [$fromCommand]);
        $this->createFixtureHost('base', 'template', [], 'check-disk', [$inherited]);
        $child = $this->createFixtureHost('child', 'template', ['base'], null, [$own]);

        $offered = self::callMethod(
            new ObjectCustomvarForm($this->getDb(), $child, [Uuid::fromBytes($added->get('uuid'))->toString()]),
            'getCustomVars',
            []
        );

        $this->assertContains($this->fx('inherited'), $offered);
        $this->assertContains($this->fx('from-command'), $offered);
        $this->assertNotContains($this->fx('own'), $offered);
        $this->assertNotContains($this->fx('added'), $offered);
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }
}
