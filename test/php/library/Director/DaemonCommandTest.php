<?php

namespace Tests\Icinga\Module\Director;

use Icinga\Application\Config;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Core\RestApiResponse;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Deployment\ConditionalDeployment;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Test\BaseTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Daemon kickstart startup
 */
class DaemonCommandTest extends BaseTestCase
{
    /** @var Db&MockObject */
    private $connection;

    private bool $transactionStarted = false;

    /** @var ?array<string, mixed> */
    private ?array $originalKickstartConfig = null;

    private bool $hadKickstartConfig = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->skipForMissingDb();

        $db = $this->getDb();
        // Storing a new generated config starts its own transaction.
        IcingaConfig::generate($db);
        $db->getDbAdapter()->beginTransaction();
        $this->transactionStarted = true;

        $config = Config::module('director', 'kickstart');
        $this->hadKickstartConfig = $config->hasSection('config');
        $this->originalKickstartConfig = $config->getSection('config')->toArray();
        $config->setSection('config', [
            'endpoint' => '___TEST___daemon-endpoint',
            'username' => '___TEST___daemon-apiuser',
        ]);

        $this->connection = $this->getMockBuilder(Db::class)
            ->setConstructorArgs([$db->getConfig()])
            ->onlyMethods(['getDbAdapter', 'getDeploymentEndpoint'])
            ->getMock();
        $this->connection->expects($this->atLeastOnce())->method('getDbAdapter')->willReturn($db->getDbAdapter());
        $db->getDbAdapter()->delete('director_deployment_log');

        IcingaApiUser::create([
            'object_name' => '___TEST___daemon-apiuser',
            'object_type' => 'external_object',
            'password' => 'test-password',
        ], $this->connection)->store();
    }

    public function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                $this->getDb()->getDbAdapter()->rollBack();
                $this->getDb()->settings()->clearCache();
            }
        } finally {
            if ($this->originalKickstartConfig !== null) {
                $config = Config::module('director', 'kickstart');
                if ($this->hadKickstartConfig) {
                    $config->setSection('config', $this->originalKickstartConfig);
                } else {
                    $config->removeSection('config');
                }
            }

            parent::tearDown();
        }
    }

    /**
     * @return void
     */
    public function testCompletedKickstartWithoutPendingDeploymentSkipsApi(): void
    {
        $this->connection->expects($this->never())->method('getDeploymentEndpoint');

        $this->runKickstart();

        self::assertFalse(DirectorDeploymentLog::hasDeployments($this->connection));
    }

    /**
     * @return void
     */
    public function testUnforcedDeploymentRetriesFailedDumpAndSkipsSuccessfulDump(): void
    {
        $config = IcingaConfig::generate($this->connection);
        $client = $this->createMock(RestApiClient::class);
        $client->expects($this->exactly(2))->method('post')->willReturnOnConsecutiveCalls(
            RestApiResponse::fromJsonResult('{"results":[{"code":500,"status":"Stage creation failed"}]}'),
            RestApiResponse::fromJsonResult(
                '{"results":[{"package":"director","stage":"unforced-recovery","code":200}]}'
            )
        );
        $deploymentApi = $this->createDeploymentApi($client);
        $api = $this->createMock(CoreApi::class);
        $api->expects($this->once())->method('collectLogFiles')->with($this->connection);
        $api->expects($this->once())->method('wipeInactiveStages')->with($this->connection);
        $api->method('getActiveStageName')->willReturn(null);
        $api->expects($this->exactly(2))->method('dumpConfig')->willReturnCallback([$deploymentApi, 'dumpConfig']);
        $deployer = new ConditionalDeployment($this->connection, $api);

        try {
            $deployer->deploy($config);
            self::fail('A rejected dump must fail an unforced deployment');
        } catch (IcingaException $exception) {
            self::assertStringContainsString('Failed to deploy config', $exception->getMessage());
        }

        $failedDeployment = DirectorDeploymentLog::loadLatest($this->connection);
        self::assertSame('n', $failedDeployment->get('dump_succeeded'));
        self::assertSame($config->getHexChecksum(), $failedDeployment->getConfigHexChecksum());

        $deployment = $deployer->deploy($config);

        self::assertInstanceOf(DirectorDeploymentLog::class, $deployment);
        self::assertSame('y', $deployment->get('dump_succeeded'));
        self::assertSame('unforced-recovery', $deployment->get('stage_name'));
        self::assertFalse($deployer->hasBeenForced());
        self::assertNull($deployer->deploy($config));
        self::assertSame('Config matches last deployed one', $deployer->getNoDeploymentReason());
    }

    /**
     * @return void
     */
    public function testKickstartGuardOnlyBlocksImportedObjects(): void
    {
        // The base fixture supplies an imported zone even in a fresh test database.
        $this->connection->getDbAdapter()->delete('icinga_zone', ['object_name = ?' => 'director-global']);
        $command = $this->getMockBuilder(DaemonCommand::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['db'])
            ->getMock();
        $command->expects($this->never())->method('db');

        $cases = [
            'no endpoints, zones or commands' => [null, null, false],
            'manual endpoint' => ['endpoint', 'object', false],
            'manual zone' => ['zone', 'object', false],
            'manual command' => ['command', 'object', false],
            'endpoint template' => ['endpoint', 'template', false],
            'zone template' => ['zone', 'template', false],
            'command template' => ['command', 'template', false],
            'imported endpoint' => ['endpoint', 'external_object', true],
            'imported zone' => ['zone', 'external_object', true],
            'imported command' => ['command', 'external_object', true],
        ];
        foreach ($cases as $label => [$type, $objectType, $expected]) {
            $object = null;
            if ($type !== null) {
                $object = $this->newObject($type, '___TEST___kickstart-guard', ['object_type' => $objectType]);
                $object->store();
            }

            self::assertSame(
                $expected,
                self::callMethod($command, 'hasExistingKickstartObjects', [$this->connection]),
                $label
            );

            if ($object !== null) {
                $object->delete();
            }
        }
    }

    /**
     * Keep dump persistence real while replacing API initialization and requests
     *
     * @param RestApiClient&MockObject $client
     *
     * @return CoreApi
     */
    private function createDeploymentApi(RestApiClient $client): CoreApi
    {
        $client->method('getPeerIdentity')->willReturn('___TEST___daemon-endpoint');

        $api = $this->getMockBuilder(CoreApi::class)
            ->setConstructorArgs([$client])
            ->onlyMethods(['assertPackageExists', 'enableWorkaroundForConnectionIssues'])
            ->getMock();
        $api->expects($this->atLeastOnce())->method('assertPackageExists')->willReturnSelf();

        return $api;
    }

    /**
     * Run the startup prerequisite without constructing the CLI application
     *
     * @return void
     */
    private function runKickstart(): void
    {
        $command = $this->getMockBuilder(DaemonCommand::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['db'])
            ->getMock();
        $command->expects($this->once())->method('db')->willReturn($this->connection);

        self::callMethod($command, 'runKickstart', [null]);
    }
}
