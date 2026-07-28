<?php

namespace Tests\Icinga\Module\Director\RestApi;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\RestApi\IcingaObjectHandler;
use Icinga\Module\Director\RestApi\IcingaObjectWriteRequest;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Web\Request;
use Icinga\Web\Response;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use ReflectionMethod;
use Ramsey\Uuid\Uuid;
use Throwable;

class IcingaObjectHandlerTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const TEMPLATE_NAME = self::PREFIX . 'webserver-template';
    private const DB_CONNECTION_KEY = self::PREFIX . 'database_connection';
    private const REGION_KEY = self::PREFIX . 'region';

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
        $method->setAccessible(true);

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
        $method->setAccessible(true);

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
        $method->setAccessible(true);

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
        }

        parent::tearDown();
    }
}
