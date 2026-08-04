<?php

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\RestApi\IcingaObjectWriteRequest;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Web\Request;
use Icinga\Web\Response;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use ReflectionMethod;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

class IcingaObjectHandlerTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_NAME = self::PREFIX . 'webserver-template';
    private const DB_CONNECTION_KEY = self::PREFIX . 'database_connection';
    private const REGION_KEY = self::PREFIX . 'region';
    private const CYCLE_HOST_CHILD = self::PREFIX . 'linux-server';
    private const CYCLE_HOST_PARENT = self::PREFIX . 'generic-host';
    private const CYCLE_COMMAND_CHILD = self::PREFIX . 'check_http';
    private const CYCLE_COMMAND_PARENT = self::PREFIX . 'plugin-check-command';
    private const SERVICE_HOST_A = self::PREFIX . 'web1.example.com';
    private const SERVICE_HOST_B = self::PREFIX . 'web2.example.com';
    private const SHARED_SERVICE_NAME = self::PREFIX . 'ssh';
    private const SSH_PORT_KEY = self::PREFIX . 'ssh_port';

    public function testObjectChangeAndCustomVarValidationFailureRollBackTogether(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name'  => self::TEMPLATE_NAME,
            'object_type'  => 'template',
            'display_name' => 'Webserver Template',
        ]);
        $host->store($db);

        $dictProperty = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::DB_CONNECTION_KEY,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Database connection',
        ], $db);
        $dictProperty->store();

        $dba = $db->getDbAdapter();
        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($dictProperty->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        $handler = new IcingaObjectHandler(new Request(), new Response(), $db);
        $method = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

        $writeRequest = new IcingaObjectWriteRequest(
            $host,
            ['display_name' => 'Webserver Template (renamed)'],
            'host',
            'index',
            'PUT',
            false,
            // A fixed-dictionary custom variable given a plain (non-associative)
            // array is a type mismatch, mirroring CustomVariableValueApplierTest's
            // own testFailedValidationRollsBackFullReplace scenario.
            [self::DB_CONNECTION_KEY => ['not', 'a', 'dictionary']],
            false,
            new UrlParams()
        );

        $threw = false;
        try {
            $db->runFailSafeTransaction(function () use ($method, $handler, $writeRequest) {
                $method->invoke($handler, $writeRequest);
            });
        } catch (Throwable $e) {
            $threw = true;
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
        }

        $this->assertTrue($threw, 'The mismatched custom variable type must raise an exception');

        $reloaded = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals(
            'Webserver Template',
            $reloaded->get('display_name'),
            'The object property change must not survive a custom-variable validation '
            . 'failure that happens in the same request'
        );
        $this->assertNull(
            $reloaded->vars()->get(self::DB_CONNECTION_KEY),
            'The rejected custom variable must not have been persisted either'
        );
    }

    public function testVarsOnlyPostReportsSuccessNotNotModified(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name'  => self::TEMPLATE_NAME,
            'object_type'  => 'template',
            'display_name' => 'Webserver Template',
        ]);
        $host->store($db);

        $regionProperty = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::REGION_KEY,
            'value_type' => 'string',
            'label'      => 'Region',
        ], $db);
        $regionProperty->store();

        $dba = $db->getDbAdapter();
        $dba->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($regionProperty->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        $response = new Response();
        $handler = new IcingaObjectHandler(new Request(), $response, $db);
        $method = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

        // Mirrors a base-object POST body of {"vars": {"region": "us-east"}} with
        // no other property changes, same as handleApiRequest() builds for a
        // vars-only POST.
        $writeRequest = new IcingaObjectWriteRequest(
            $host,
            [],
            'host',
            'index',
            'POST',
            true,
            [self::REGION_KEY => 'us-east'],
            false,
            new UrlParams()
        );

        $db->runFailSafeTransaction(function () use ($method, $handler, $writeRequest) {
            $method->invoke($handler, $writeRequest);
        });

        $this->assertEquals(
            200,
            $response->getHttpResponseCode(),
            'A vars-only POST that really mutates data must not report 304 Not Modified'
        );

        $reloaded = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $this->assertEquals(
            'us-east',
            $reloaded->vars()->get(self::REGION_KEY)->getValue(),
            'The custom variable must actually have been persisted'
        );
    }

    public function testNoOpVarsPostKeepsNotModifiedStatus(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name'  => self::TEMPLATE_NAME,
            'object_type'  => 'template',
            'display_name' => 'Webserver Template',
        ]);
        $host->store($db);

        $response = new Response();
        $handler = new IcingaObjectHandler(new Request(), $response, $db);
        $method = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

        // Null for a variable that was never set is a true no-op, nothing to
        // wipe or write. Unlike the real mutation above, this must keep 304.
        $writeRequest = new IcingaObjectWriteRequest(
            $host,
            [],
            'host',
            'index',
            'POST',
            true,
            [self::REGION_KEY => null],
            false,
            new UrlParams()
        );

        $db->runFailSafeTransaction(function () use ($method, $handler, $writeRequest) {
            $method->invoke($handler, $writeRequest);
        });

        $this->assertEquals(
            304,
            $response->getHttpResponseCode(),
            'A vars override that resolves to a true no-op must keep reporting 304 Not Modified'
        );
    }

    public function testServiceVariablesWriteReloadsTheRightServiceWhenNamesCollideAcrossHosts(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->deleteServiceFixtures($db);

        $hostA = IcingaHost::create([
            'object_name' => self::SERVICE_HOST_A,
            'object_type' => 'object',
            'address'     => '127.0.0.1',
        ]);
        $hostA->store($db);

        $hostB = IcingaHost::create([
            'object_name' => self::SERVICE_HOST_B,
            'object_type' => 'object',
            'address'     => '127.0.0.2',
        ]);
        $hostB->store($db);

        $serviceA = IcingaService::create([
            'object_name' => self::SHARED_SERVICE_NAME,
            'object_type' => 'object',
            'host_id'     => $hostA->get('id'),
        ]);
        $serviceA->store($db);

        $serviceB = IcingaService::create([
            'object_name' => self::SHARED_SERVICE_NAME,
            'object_type' => 'object',
            'host_id'     => $hostB->get('id'),
        ]);
        $serviceB->store($db);

        // A plain object can only get a custom variable that has already been
        // declared and attached to it (or to one of its templates).
        $sshPortProperty = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::SSH_PORT_KEY,
            'value_type' => 'string',
            'label'      => 'SSH Port',
        ], $db);
        $sshPortProperty->store();

        $dba = $db->getDbAdapter();
        $dba->insert('icinga_service_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($sshPortProperty->get('uuid'), $dba),
            'service_uuid'  => DbUtil::quoteBinaryCompat($serviceA->get('uuid'), $dba),
        ]);

        $handler = new IcingaObjectHandler(new Request(), new Response(), $db);
        $method = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

        // Both services share an object_name, only host_id tells them apart.
        // A reload keyed on object_name alone (the old code) can't tell which
        // one to load, and a composite key given a scalar id throws outright.
        $writeRequest = new IcingaObjectWriteRequest(
            $serviceA,
            [],
            'service',
            'variables',
            'PUT',
            false,
            [self::SSH_PORT_KEY => '2222'],
            false,
            new UrlParams()
        );

        $result = null;
        $db->runFailSafeTransaction(function () use ($method, $handler, $writeRequest, &$result) {
            $result = $method->invoke($handler, $writeRequest);
        });

        $this->assertNotNull($result, 'a service variables write must reload the object, not throw');
        $this->assertEquals(
            Uuid::fromBytes($serviceA->get('uuid'))->toString(),
            $result->getUniqueId()->toString(),
            'the reloaded object must be the service the request actually targeted'
        );

        $reloadedA = IcingaService::loadWithUniqueId(Uuid::fromBytes($serviceA->get('uuid')), $db);
        $this->assertEquals('2222', $reloadedA->vars()->get(self::SSH_PORT_KEY)->getValue());

        $reloadedB = IcingaService::loadWithUniqueId(Uuid::fromBytes($serviceB->get('uuid')), $db);
        $this->assertNull(
            $reloadedB->vars()->get('ssh_port'),
            'the other host, same-named service must not have picked up the write'
        );
    }

    public function testRejectsVariablesOnAnObjectThatWasNeverCreated(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $handler = new IcingaObjectHandler(new Request(), new Response(), $db);
        $method = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

        // No object_name in the body, so createByType() builds an object that
        // persistChanges() finds unmodified and never stores.
        $writeRequest = new IcingaObjectWriteRequest(
            null,
            [],
            'host',
            'index',
            'POST',
            true,
            [self::REGION_KEY => 'us-east'],
            false,
            new UrlParams()
        );

        $this->expectException(InvalidArgumentException::class);
        $method->invoke($handler, $writeRequest);
    }

    public static function cycleScenarioProvider(): array
    {
        return [
            'host POST'    => ['host', 'POST'],
            'host PUT'     => ['host', 'PUT'],
            'command POST' => ['command', 'POST'],
            'command PUT'  => ['command', 'PUT'],
        ];
    }

    /**
     * @dataProvider cycleScenarioProvider
     */
    public function testIndirectImportCycleIsRejectedBeforeBeingPersisted(string $type, string $method): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        // Template tree is cached per type for the whole run and never refreshes
        // itself, so clear it or an earlier test leaves us with stale ancestry.
        IcingaTemplateRepository::clear();

        // "linux-server" already imports "generic-host" (and "check_http" already
        // imports "plugin-check-command"), like most real Director setups. Making
        // the base template import the child back would close a two-node loop.
        [$child, $parent] = $type === 'host'
            ? [self::CYCLE_HOST_CHILD, self::CYCLE_HOST_PARENT]
            : [self::CYCLE_COMMAND_CHILD, self::CYCLE_COMMAND_PARENT];

        $this->deleteIfExists($type, $child, $db);
        $this->deleteIfExists($type, $parent, $db);

        $class = $type === 'host' ? IcingaHost::class : IcingaCommand::class;

        try {
            $parentTemplate = $class::create([
                'object_name' => $parent,
                'object_type' => 'template',
            ], $db);
            $parentTemplate->store($db);

            $childTemplate = $class::create([
                'object_name' => $child,
                'object_type' => 'template',
                'imports'     => [$parent],
            ], $db);
            $childTemplate->store($db);

            $handler = new IcingaObjectHandler(new Request(), new Response(), $db);
            $reflectionMethod = new ReflectionMethod($handler, 'persistObjectAndApplyVars');

            $data = $method === 'PUT'
                ? ['object_type' => 'template', 'imports' => [$child]]
                : ['imports' => [$child]];

            $writeRequest = new IcingaObjectWriteRequest(
                $parentTemplate,
                $data,
                $type,
                'index',
                $method,
                false,
                [],
                false,
                new UrlParams()
            );

            $threw = false;
            try {
                $db->runFailSafeTransaction(function () use ($reflectionMethod, $handler, $writeRequest) {
                    $reflectionMethod->invoke($handler, $writeRequest);
                });
            } catch (Throwable $e) {
                $threw = true;
                $this->assertInstanceOf(RuntimeException::class, $e);
            }

            $this->assertTrue($threw, 'Closing an indirect two-node import cycle must raise an exception');

            $reloaded = $class::load($parent, $db);
            $this->assertSame(
                [],
                $reloaded->getImports(),
                'The rejected import cycle must not have been persisted'
            );
        } finally {
            $this->deleteIfExists($type, $child, $db);
            $this->deleteIfExists($type, $parent, $db);
        }
    }

    private function deleteIfExists(string $type, string $name, $db): void
    {
        $class = $type === 'host' ? IcingaHost::class : IcingaCommand::class;
        if ($class::exists($name, $db)) {
            $class::load($name, $db)->delete();
        }
    }

    public function testDeleteIsAllowedOnTheIndexAction(): void
    {
        IcingaObjectHandler::assertDeleteAllowed('index');
        $this->addToAssertionCount(1);
    }

    public function testDeleteIsRejectedOnTheVariablesAction(): void
    {
        $this->expectException(NotFoundError::class);
        IcingaObjectHandler::assertDeleteAllowed('variables');
    }

    public function testJsonObjectBodyIsAccepted(): void
    {
        IcingaObjectHandler::assertJsonBodyIsObject((object) ['environment' => 'production']);
        $this->addToAssertionCount(1);
    }

    public function testJsonArrayBodyIsRejected(): void
    {
        // InvalidArgumentException is what processApiRequest() maps to HTTP 422,
        // the same status every other malformed override in this handler returns.
        $this->expectException(InvalidArgumentException::class);
        IcingaObjectHandler::assertJsonBodyIsObject([1, 2, 3]);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
                $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
                $dba->delete(
                    'icinga_host_property',
                    $dba->quoteInto(
                        'host_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                    )
                );
                $host->delete();
            }

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::DB_CONNECTION_KEY));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::REGION_KEY));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::SSH_PORT_KEY));

            $this->deleteServiceFixtures($db);
        }

        parent::tearDown();
    }

    private function deleteServiceFixtures($db): void
    {
        // deleting the host cascades to its services
        foreach ([self::SERVICE_HOST_A, self::SERVICE_HOST_B] as $hostName) {
            if (IcingaHost::exists($hostName, $db)) {
                IcingaHost::load($hostName, $db)->delete();
            }
        }
    }
}
