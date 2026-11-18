<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Security\SecurityException;
use Icinga\User;
use Ramsey\Uuid\Uuid;
use ReflectionProperty;
use Tests\Icinga\Module\Director\Lib\Form\TestablePropertiesDictionary;

class CustomVariablesFormHostFilterTest extends BaseTestCase
{
    private const PREFIX = '___TEST___cv_filter_';
    private const HOST = self::PREFIX . 'host';
    private const TEAM = self::PREFIX . 'team';

    /** @var ?DirectorProperty */
    private $property;

    public function setUp(): void
    {
        parent::setUp();
        if (! $this->hasDb()) {
            return;
        }

        $this->cleanArtifacts();
        $db = $this->getDb();
        $this->property = DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => self::TEAM,
            'value_type' => 'string',
        ], $db);
        $this->property->store();

        IcingaHost::create([
            'object_name' => self::HOST,
            'object_type' => 'object',
            'vars.' . self::TEAM => 'Team_A',
        ], $db)->store();

        $user = new User(self::PREFIX . 'user');
        $user->setRestrictions([Restriction::FILTER_HOSTS => ['host.vars.' . self::TEAM . '=Team_*']]);
        Auth::getInstance()->setUser($user);
    }

    protected function tearDown(): void
    {
        // don't leak the fake user into other tests
        $user = new ReflectionProperty(Auth::class, 'user');
        $user->setAccessible(true);
        $user->setValue(Auth::getInstance(), null);

        if ($this->hasDb()) {
            $this->cleanArtifacts();
        }

        parent::tearDown();
    }

    public function testVariablesKeepingTheHostInTheFilterCanBeChanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        self::callMethod($this->formSubmitting('Team_B'), 'onSuccess', []);

        $this->assertSame('Team_B', $this->storedTeam());
    }

    public function testLeavingTheFilterIsRejectedAndRolledBack(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        try {
            self::callMethod($this->formSubmitting('Other'), 'onSuccess', []);
            $this->fail('Moving the host out of the filter must fail');
        } catch (SecurityException $e) {
            $this->assertSame('Team_A', $this->storedTeam());
        }
    }

    private function formSubmitting(string $team): CustomVariablesForm
    {
        $form = new CustomVariablesForm(IcingaHost::load(self::HOST, $this->getDb()), [
            self::TEAM => [
                'uuid' => $this->property->get('uuid'),
                'value_type' => 'string',
                'value' => 'Team_A',
            ],
        ]);
        $form->registerElement(new TestablePropertiesDictionary([self::TEAM => $team]));

        return $form;
    }

    private function storedTeam(): string
    {
        return IcingaHost::load(self::HOST, $this->getDb())->vars()->get(self::TEAM)->getValue();
    }

    private function cleanArtifacts(): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $dba->delete('icinga_host', $dba->quoteInto('object_name = ?', self::HOST));
        $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::TEAM));
    }
}
