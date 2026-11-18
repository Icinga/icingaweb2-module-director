<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\RestApi\IcingaObjectWriteRequest;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Security\SecurityException;
use Icinga\User;
use Icinga\Web\Request;
use Icinga\Web\Response;
use Icinga\Web\UrlParams;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

/**
 * REST API writes through IcingaObjectHandler, for a user with director/filter/hosts
 */
class RestrictedWriteGuardTest extends BaseTestCase
{
    private const PREFIX = '___TEST___guard_';
    private const TEMPLATE = self::PREFIX . 'tpl';
    private const ALLOWED_HOST = self::PREFIX . 'allowed';
    private const OTHER_HOST = self::PREFIX . 'other';
    private const TEAM = self::PREFIX . 'team';
    private const MAIL = self::PREFIX . 'mail';

    public function setUp(): void
    {
        parent::setUp();
        if (! $this->hasDb()) {
            return;
        }

        $this->cleanArtifacts();
        $db = $this->getDb();

        $template = IcingaHost::create(['object_name' => self::TEMPLATE, 'object_type' => 'template'], $db);
        $template->store();
        $dba = $db->getDbAdapter();
        foreach ([self::TEAM, self::MAIL] as $keyName) {
            $property = DirectorProperty::create([
                'uuid'       => Uuid::uuid4()->getBytes(),
                'key_name'   => $keyName,
                'value_type' => 'string',
            ], $db);
            $property->store();
            $dba->insert('icinga_host_property', [
                'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
                'host_uuid'     => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
            ]);
        }

        foreach ([self::ALLOWED_HOST => 'Team_A', self::OTHER_HOST => 'Team_B'] as $name => $team) {
            IcingaHost::create([
                'object_name'        => $name,
                'object_type'        => 'object',
                'imports'            => [self::TEMPLATE],
                'vars.' . self::TEAM => $team,
                'vars.' . self::MAIL => 'yes',
            ], $db)->store();
        }

        $user = new User(self::PREFIX . 'user');
        $user->setRestrictions([Restriction::FILTER_HOSTS => ['host.vars.' . self::TEAM . '=Team_A']]);
        Auth::getInstance()->setUser($user);
    }

    protected function tearDown(): void
    {
        $user = new ReflectionProperty(Auth::class, 'user');
        $user->setAccessible(true);
        $user->setValue(Auth::getInstance(), null);

        if ($this->hasDb()) {
            $this->cleanArtifacts();
        }

        parent::tearDown();
    }

    public function testChangesKeepingTheHostInTheFilterAreAllowed(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->write(IcingaHost::load(self::ALLOWED_HOST, $this->getDb()), [self::MAIL => 'no']);

        $this->assertSame('no', $this->varOf(self::ALLOWED_HOST, self::MAIL));
    }

    public function testLeavingTheFilterIsRejectedAndRolledBack(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $error = $this->failingWrite(
            IcingaHost::load(self::ALLOWED_HOST, $this->getDb()),
            [self::MAIL => 'no', self::TEAM => 'Team_B']
        );

        $this->assertInstanceOf(SecurityException::class, $error);
        $this->assertSame('Team_A', $this->varOf(self::ALLOWED_HOST, self::TEAM));
        $this->assertSame('yes', $this->varOf(self::ALLOWED_HOST, self::MAIL), 'Nothing must be stored');
    }

    public function testHostOutsideTheFilterCannotBeCreated(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $name = self::PREFIX . 'new';
        $error = $this->failingWrite(null, [], ['object_name' => $name, 'object_type' => 'object']);

        $this->assertInstanceOf(SecurityException::class, $error);
        $this->assertFalse(IcingaHost::exists($name, $this->getDb()));
    }

    public function testServiceOverridesOnHostsOutsideTheFilterAreNotFound(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        // Same setup as IcingaObjectHandlerTest::testServiceOverrideDoesNotRespondBeforeItIsActuallyPersisted()
        IcingaService::create([
            'object_name'            => self::PREFIX . 'ping',
            'object_type'            => 'object',
            'host_id'                => IcingaHost::load(self::OTHER_HOST, $db)->get('id'),
            'vars.use_var_overrides' => 'y',
        ], $db)->store();

        $params = new UrlParams();
        $params->set('host', self::OTHER_HOST);
        $params->set('name', self::PREFIX . 'ping');
        $request = new IcingaObjectWriteRequest(
            null,
            [],
            'service',
            'index',
            'POST',
            false,
            [self::MAIL => 'no'],
            true,
            $params
        );

        try {
            $this->invoke($request);
            $this->fail('The override must be rejected');
        } catch (NotFoundError $e) {
            $this->assertEquals(
                (object) [],
                IcingaHost::load(self::OTHER_HOST, $db)->getOverriddenServiceVars(self::PREFIX . 'ping')
            );
        }
    }

    /**
     * @param ?IcingaHost $host
     * @param array<string, mixed> $vars
     * @param array<string, mixed> $data
     */
    private function write(?IcingaHost $host, array $vars, array $data = []): void
    {
        $request = new IcingaObjectWriteRequest(
            $host,
            $data,
            'host',
            'index',
            'POST',
            false,
            $vars,
            false,
            new UrlParams()
        );

        $this->getDb()->runFailSafeTransaction(function () use ($request) {
            $this->invoke($request);
        });
    }

    /**
     * @return ?Throwable The error the write failed with
     */
    private function failingWrite(?IcingaHost $host, array $vars, array $data = []): ?Throwable
    {
        try {
            $this->write($host, $vars, $data);
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The write must fail');
    }

    private function invoke(IcingaObjectWriteRequest $request)
    {
        $handler = new IcingaObjectHandler(new Request(), new Response(), $this->getDb());

        return (new ReflectionMethod($handler, 'persistObjectAndApplyVars'))->invoke($handler, $request);
    }

    private function varOf(string $hostName, string $varName)
    {
        return IcingaHost::load($hostName, $this->getDb())->vars()->get($varName)->getValue();
    }

    private function cleanArtifacts(): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $where = $dba->quoteInto('object_name LIKE ?', self::PREFIX . '%');
        $dba->delete('icinga_host', $where . " AND object_type = 'object'");
        $dba->delete('icinga_host', $where);
        $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [self::TEAM, self::MAIL]));
    }
}
