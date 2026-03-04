<?php

namespace Tests\Icinga\Module\Director;

use Icinga\Application\Config;
use Icinga\Module\Director\Clicommands\DaemonCommand;
use Icinga\Module\Director\Db;
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
