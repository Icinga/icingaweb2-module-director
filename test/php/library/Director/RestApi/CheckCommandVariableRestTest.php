<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\RestApi\CustomVarApplyRequest;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\RestApi\IcingaObjectWriteRequest;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Web\Request;
use Icinga\Web\Response;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;
use Tests\Icinga\Module\Director\Lib\CustomVariable\CommandVariableFixture;

class CheckCommandVariableRestTest extends BaseTestCase
{
    use CommandVariableFixture;

    public function testTheBaseEndpointTypeChecksAndLinksACheckCommandVariable(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $host = $this->createFixtureHost('web01', 'object', [], 'check-disk');

        try {
            $this->writeObject($host, 'POST', [], ['warning' => ['80%']]);
            $this->fail('a value of the wrong shape must be rejected');
        } catch (InvalidArgumentException $e) {
            $this->assertFalse($this->fetchFixtureLink($host, 'warning'));
        }

        $this->writeObject($host, 'POST', [], ['warning' => '80%']);

        $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($host, 'warning'));
    }

    public function testThePostOfAVariableTheSameRequestMakesUnreachableLosesIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $host = $this->createFixtureHost('web01', 'object', [], 'check-disk');
        $this->storeFixtureValue($host, 'warning', '80%', $warning);

        $this->writeObject($host, 'POST', ['check_command' => $this->fx('check-ping')], ['warning' => 'again']);

        $this->assertFalse($this->fetchFixtureLink($host, 'warning'));
    }

    public function testThePutOfAVariableTheSameRequestMakesUnreachableStoresItPlain(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureCommand('check-ping', 'object');
        $host = $this->createFixtureHost('web01', 'object', [], 'check-disk');
        $this->storeFixtureValue($host, 'warning', '80%', $warning);

        $this->writeObject($host, 'PUT', ['check_command' => $this->fx('check-ping')], ['warning' => 'again']);

        $this->assertNull($this->fetchFixtureLink($host, 'warning'));
        $this->assertSame(
            'again',
            IcingaHost::load($this->fx('web01'), $this->getDb())->get('vars.' . $this->fx('warning'))
        );
    }

    public function testTheVariablesEndpointStoresACheckCommandVariableOnAHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $this->createFixtureHost('linux', 'template', [], 'check-disk');
        $host = $this->createFixtureHost('web01', 'object', ['linux']);

        $this->applyVariables($host, 'PUT', ['warning' => '80%']);

        $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($host, 'warning'));
        $this->assertSame(0, $this->countAttachments($host));
        $this->expectException(InvalidArgumentException::class);
        $this->applyVariables($host, 'POST', ['warning' => ['80%']]);
    }

    public function testReplacingTheVariablesOfATemplateTakesOverACheckCommandVariableOnce(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $warning = $this->createFixtureProperty('warning');
        $this->createFixtureCommand('check-disk', 'object', [], [$warning]);
        $template = $this->createFixtureHost('linux', 'template', [], 'check-disk');

        $this->applyVariables($template, 'POST', ['warning' => '90%']);
        $this->assertSame(0, $this->countAttachments($template), 'POST stores the value only');

        $this->applyVariables($template, 'PUT', ['warning' => '80%']);
        $this->applyVariables($template, 'PUT', ['warning' => '70%']);

        $this->assertSame(1, $this->countAttachments($template));
        $this->assertSame($warning->get('uuid'), $this->fetchFixtureLink($template, 'warning'));
    }

    public function testReplacingTheVariablesOfACommandTemplateTakesNothingOverFromItsImports(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $shared = $this->createFixtureProperty('shared');
        $mode = $this->createFixtureProperty('mode');
        $this->createFixtureCommand('plugin-plain', 'object', [], [$shared]);
        $this->createFixtureCommand('plugin-base', 'template', [], [$mode]);
        $template = $this->createFixtureCommand('plugin', 'template', ['plugin-plain', 'plugin-base']);

        $this->applyVariables($template, 'PUT', ['shared' => 'a', 'mode' => 'fast']);

        $this->assertSame(
            0,
            $this->countAttachments($template),
            'the imports of a command are its templates, not a check command to take over from'
        );
        $this->assertSame($shared->get('uuid'), $this->fetchFixtureLink($template, 'shared'));
        $this->assertSame($mode->get('uuid'), $this->fetchFixtureLink($template, 'mode'));
    }

    public function testTheErrorForAnUnreachableVariableNamesTheCheckCommandOnHostsOnly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtureProperty('elsewhere');
        $host = $this->createFixtureHost('web01');
        $user = IcingaUser::create(['object_name' => $this->fx('alice'), 'object_type' => 'object'], $this->getDb());
        $user->store();

        try {
            $this->applyVariables($host, 'POST', ['elsewhere' => 'x']);
            $this->fail('an unreachable variable must be rejected');
        } catch (NotFoundError $e) {
            $this->assertStringContainsString('or to the check command for this object', $e->getMessage());
        }

        try {
            $this->applyVariables($user, 'POST', ['elsewhere' => 'x']);
            $this->fail('an unreachable variable must be rejected');
        } catch (NotFoundError $e) {
            $this->assertStringEndsWith(
                'should be first added to one of the imported templates for this object',
                $e->getMessage()
            );
        } finally {
            $user->delete();
        }
    }

    public function tearDown(): void
    {
        $this->removeFixtures();
        parent::tearDown();
    }

    /**
     * Send a write to the base endpoint of an object, like `POST director/host`
     *
     * @param IcingaObject         $object
     * @param string               $method
     * @param array<string, mixed> $data   Object properties
     * @param array<string, mixed> $vars   Unprefixed variable names and values
     *
     * @return void
     */
    private function writeObject(IcingaObject $object, string $method, array $data, array $vars): void
    {
        $db = $this->getDb();
        // the REST controller loads through the object store, which rebuilds the object without value links
        $object = (new DbObjectStore($db, new Branch()))->load(
            $object->getTableName(),
            Uuid::fromBytes(DbUtil::binaryResult($object->get('uuid')))
        );
        assert($object instanceof IcingaObject);
        $handler = new IcingaObjectHandler(new Request(), new Response(), $db);
        $request = new IcingaObjectWriteRequest(
            $object,
            $data,
            $object->getShortTableName(),
            'index',
            $method,
            $method === 'POST',
            $this->prefixKeys($vars),
            false,
            new UrlParams()
        );

        $db->runFailSafeTransaction(function () use ($handler, $request): void {
            (new ReflectionMethod($handler, 'persistObjectAndApplyVars'))->invoke($handler, $request);
        });
    }

    /**
     * Send values to the variables endpoint of an object, like `PUT director/host/variables`
     *
     * @param IcingaObject         $object
     * @param string               $method
     * @param array<string, mixed> $vars   Unprefixed variable names and values
     *
     * @return void
     */
    private function applyVariables(IcingaObject $object, string $method, array $vars): void
    {
        $object = $object::loadWithAutoIncId((int) $object->get('id'), $this->getDb());
        (new CustomVariableValueApplier($this->getDb()))->apply(
            new CustomVarApplyRequest($object, $this->prefixKeys($vars), 'variables', $method, false)
        );
    }

    /**
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    private function prefixKeys(array $vars): array
    {
        $prefixed = [];
        foreach ($vars as $name => $value) {
            $prefixed[$this->fx($name)] = $value;
        }

        return $prefixed;
    }

    private function countAttachments(IcingaObject $object): int
    {
        $type = $object->getShortTableName();
        $dba = $this->getDb()->getDbAdapter();

        return (int) $dba->fetchOne(
            $dba->select()
                ->from(['iop' => 'icinga_' . $type . '_property'], ['COUNT(*)'])
                ->join(['o' => 'icinga_' . $type], "o.uuid = iop.{$type}_uuid", [])
                ->where('o.id = ?', (int) $object->get('id'))
        );
    }
}
