<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use React\EventLoop\LoopInterface;

use function React\Promise\resolve;

class DeploymentChecker implements DbBasedComponent
{
    /** @var Db */
    protected $connection;

    public function __construct(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(5, function () {
            if ($db = $this->connection) {
                try {
                    if (DirectorDeploymentLog::hasUncollected($db)) {
                        $db->getDeploymentEndpoint()->api()->collectLogFiles($db);
                    }
                } catch (Exception $e) {
                    // Ignore eventual issues while talking to Icinga
                }
            }
        });
    }

    /**
     * @param Db $connection
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $connection)
    {
        $this->connection = $connection;

        return resolve();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->connection = null;

        return resolve();
    }
}
