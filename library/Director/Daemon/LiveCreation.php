<?php

namespace Icinga\Module\Director\Daemon;

use Exception;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\IcingaModifiedAttribute;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use React\EventLoop\LoopInterface;
use function React\Promise\resolve;

class LiveCreation implements DbBasedComponent
{
    /** @var Db */
    protected $db;

    public function __construct(LoopInterface $loop)
    {
        $loop->addPeriodicTimer(5, function () {
            if ($this->db) {
                $this->run();
            }
        });
    }

    /**
     * @return IcingaModifiedAttribute[]
     */
    public function fetchPendingModifications()
    {
        return IcingaModifiedAttribute::loadAll(
            $this->db,
            $this->db->getDbAdapter()
                ->select()->from('icinga_modified_attribute')
                ->where('state != ?', 'applied')
                ->order('state')
                ->order('id')
        );
    }

    public function applyModification(IcingaModifiedAttribute $modifiedAttribute)
    {
        try {
            return $this->db->getDeploymentEndpoint()->api()->sendModification($modifiedAttribute);
        } catch (Exception $e) {
            return false;
        }
    }

    public function run()
    {
        foreach ($this->fetchPendingModifications() as $modification) {
            $activityLogStatus = null;
            $activityId = $modification->get('activity_id');
            if ($activityId !== null) {
                $activityLog = DirectorActivityLog::load($activityId, $this->db);
            }

            if ($this->applyModification($modification)) {
                if ($modification->get('state') === 'scheduled_for_reset') {
                    $modification->delete();
                } else {
                    if ($activityId !== null && $activityLog->get('live_modification') ===
                        DirectorActivityLog::LIVE_MODIFICATION_VALUE_SCHEDULED) {
                        $activityLogStatus = DirectorActivityLog::LIVE_MODIFICATION_VALUE_SUCCEEDED;
                    }
                    $modification->set('state', 'applied');
                    $modification->set('ts_applied', DaemonUtil::timestampWithMilliseconds());
                    $modification->store();
                }
            } else {
                $activityLogStatus = DirectorActivityLog::LIVE_MODIFICATION_VALUE_FAILED;
                $modification->delete();
            }

            if ($activityId !== null && $activityLogStatus !== null) {
                $activityLog->set('live_modification', $activityLogStatus);
                $activityLog->store($this->db);
            }
        }
    }

    /**
     * @param Db $connection
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function initDb(Db $connection)
    {
        $this->db = $connection;

        return resolve();
    }

    /**
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function stopDb()
    {
        $this->db = null;

        return resolve();
    }
}
