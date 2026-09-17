<?php

namespace Tests\Icinga\Module\Director;

use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\KickstartHelper;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Test\BaseTestCase;
use RuntimeException;

/**
 * Database persistence across successful and failed kickstart imports
 */
class KickstartHelperTest extends BaseTestCase
{
    /** @var string */
    private const NAME = '___TEST___kickstart';

    private bool $fixturesInitialized = false;

    private ?string $originalMasterZone = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->skipForMissingDb();

        $this->originalMasterZone = $this->getDb()->settings()->getStoredValue('master_zone');
        $this->fixturesInitialized = true;
    }

    public function tearDown(): void
    {
        try {
            if ($this->fixturesInitialized) {
                $db = $this->getDb();
                foreach (['endpoint', 'command', 'zone', 'apiuser'] as $type) {
                    if (IcingaObject::existsByType($type, self::NAME, $db)) {
                        IcingaObject::loadByType($type, self::NAME, $db)->delete();
                    }
                }

                $db->settings()->clearCache()->set('master_zone', $this->originalMasterZone);
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Roll back credentials persisted before the first API request fails
     *
     * @return void
     */
    public function testEarlyApiFailureDoesNotLeaveApiUser(): void
    {
        $helper = $this->createHelper('early');

        $this->assertRunFails($helper, 'Endpoint request failed');

        self::assertFalse(IcingaApiUser::exists(self::NAME, $this->getDb()));
        self::assertFalse(IcingaZone::exists(self::NAME, $this->getDb()));
        self::assertFalse(IcingaEndpoint::exists(self::NAME, $this->getDb()));
        self::assertFalse(IcingaCommand::exists(self::NAME, $this->getDb()));
    }

    /**
     * Restore existing objects when the import fails after storing changes
     *
     * @return void
     */
    public function testLateFailureRestoresExistingObjects(): void
    {
        $db = $this->getDb();
        IcingaApiUser::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
            'password' => 'old-password',
        ], $db)->store();
        IcingaEndpoint::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
            'host' => 'old.example.test',
        ], $db)->store();
        IcingaCommand::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
            'command' => '/old/check',
        ], $db)->store();

        $this->assertRunFails($this->createHelper('late'), 'Import cleanup failed');

        self::assertSame('old-password', IcingaApiUser::load(self::NAME, $db)->get('password'));
        self::assertSame('old.example.test', IcingaEndpoint::load(self::NAME, $db)->get('host'));
        self::assertSame('/old/check', IcingaCommand::load(self::NAME, $db)->get('command'));
        self::assertFalse(IcingaZone::exists(self::NAME, $db));
        self::assertSame(
            $this->originalMasterZone,
            $db->settings()->clearCache()->getStoredValue('master_zone')
        );
    }

    /**
     * Commit credentials, imported objects, and the deployment zone together
     *
     * @return void
     */
    public function testSuccessfulImportPersistsObjects(): void
    {
        $this->createHelper()->run();

        $db = $this->getDb();
        self::assertSame('new-password', IcingaApiUser::load(self::NAME, $db)->get('password'));
        self::assertTrue(IcingaZone::exists(self::NAME, $db));
        self::assertSame('new.example.test', IcingaEndpoint::load(self::NAME, $db)->get('host'));
        self::assertSame(self::NAME, IcingaEndpoint::load(self::NAME, $db)->get('apiuser'));
        self::assertSame('/new/check', IcingaCommand::load(self::NAME, $db)->get('command'));
        self::assertSame(self::NAME, $db->settings()->clearCache()->getStoredValue('master_zone'));
    }

    /**
     * Supply API objects while keeping the real import and database writes
     *
     * @param ?string $failure Failure at the first API request or final cleanup
     *
     * @return KickstartHelper
     */
    private function createHelper(?string $failure = null): KickstartHelper
    {
        $db = $this->getDb();
        $api = $this->getMockBuilder(CoreApi::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEndpointObjects', 'getZoneObjects', 'getSpecificCommandObjects', 'getStatus'])
            ->getMock();

        // Include existing external objects so the import does not remove unrelated fixtures.
        $zones = IcingaObject::loadAllExternalObjectsByType('zone', $db);
        $zones[self::NAME] = IcingaZone::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
        ], $db);
        $endpoints = IcingaObject::loadAllExternalObjectsByType('endpoint', $db);
        $endpoints[self::NAME] = IcingaEndpoint::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
            'host' => 'new.example.test',
            'zone' => self::NAME,
        ], $db);
        $commands = IcingaObject::loadAllExternalObjectsByType('command', $db);
        $commands[self::NAME] = IcingaCommand::create([
            'object_name' => self::NAME,
            'object_type' => 'external_object',
            'command' => '/new/check',
        ], $db);

        if ($failure === 'early') {
            $api->expects($this->once())->method('getEndpointObjects')->willThrowException(
                new RuntimeException('Endpoint request failed')
            );
        } else {
            $api->expects($this->once())->method('getEndpointObjects')->willReturn($endpoints);
        }
        $api->method('getZoneObjects')->willReturn($zones);
        $api->method('getSpecificCommandObjects')->willReturnCallback(function ($type) use ($commands) {
            return $type === 'Check' ? $commands : [];
        });

        $methods = ['getConfiguredApi', 'getDeploymentApi'];
        if ($failure === 'late') {
            $methods[] = 'removeCommands';
        }
        $helper = $this->getMockBuilder(KickstartHelper::class)
            ->setConstructorArgs([$db])
            ->onlyMethods($methods)
            ->getMock();
        $helper->expects($this->once())->method('getConfiguredApi')->willReturnCallback(
            function () use ($helper, $api) {
                // The real API factory persists credentials before making its first request.
                self::callMethod($helper, 'apiUser', []);

                return $api;
            }
        );
        $helper->method('getDeploymentApi')->willReturn($api);
        if ($failure === 'late') {
            $helper->method('removeCommands')->willThrowException(new RuntimeException('Import cleanup failed'));
        }

        return $helper->setConfig([
            'endpoint' => self::NAME,
            'username' => self::NAME,
            'password' => 'new-password',
        ]);
    }

    /**
     * Require the import to propagate the injected failure
     *
     * @param KickstartHelper $helper
     * @param string $message
     *
     * @return void
     */
    private function assertRunFails(KickstartHelper $helper, string $message): void
    {
        try {
            $helper->run();
            self::fail('Kickstart unexpectedly succeeded');
        } catch (RuntimeException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }
}
