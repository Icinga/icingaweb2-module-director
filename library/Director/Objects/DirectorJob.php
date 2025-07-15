<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Daemon\DaemonUtil;
use Icinga\Module\Director\Daemon\Logger;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Hook\JobHook;
use Exception;
use InvalidArgumentException;

class DirectorJob extends DbObjectWithSettings implements ExportInterface, InstantiatedViaHook
{
    /** @var JobHook */
    protected $job;

    protected $table = 'director_job';

    protected $keyName = 'job_name';

    protected $autoincKeyName = 'id';

    protected $protectAutoinc = false;

    protected $defaultProperties = [
        'id'                     => null,
        'job_name'               => null,
        'job_class'              => null,
        'disabled'               => null,
        'run_interval'           => null,
        'last_attempt_succeeded' => null,
        'ts_last_attempt'        => null,
        'ts_last_error'          => null,
        'last_error_message'     => null,
        'timeperiod_id'          => null,
    ];

    protected $stateProperties = [
        'last_attempt_succeeded',
        'last_error_message',
        'ts_last_attempt',
        'ts_last_error',
    ];

    protected $settingsTable = 'director_job_setting';

    protected $settingsRemoteId = 'job_id';

    public function getUniqueIdentifier()
    {
        return $this->get('job_name');
    }

    /**
     * @deprecated please use JobHook::getInstance()
     * @return JobHook
     */
    public function job()
    {
        return $this->getInstance();
    }

    /**
     * @return JobHook
     */
    public function getInstance()
    {
        if ($this->job === null) {
            $class = $this->get('job_class');
            $this->job = new $class();
            $this->job->setDb($this->connection);
            $this->job->setDefinition($this);
        }

        return $this->job;
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function run()
    {
        $job = $this->getInstance();
        $currentTimestamp = DaemonUtil::timestampWithMilliseconds();
        $this->set('ts_last_attempt', $currentTimestamp);

        try {
            $job->run();
            $this->set('last_attempt_succeeded', 'y');
            $success = true;
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            $this->set('ts_last_error', $currentTimestamp);
            $this->set('last_error_message', $e->getMessage());
            $this->set('last_attempt_succeeded', 'n');
            $success = false;
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $success;
    }

    /**
     * @return bool
     */
    public function shouldRun()
    {
        return (! $this->hasBeenDisabled()) && $this->isPending();
    }

    /**
     * @return bool
     */
    public function isOverdue()
    {
        if (! $this->shouldRun()) {
            return false;
        }

        if ($this->get('ts_last_attempt') === null) {
            return true;
        }

        return (
            $this->get('ts_last_attempt') + $this->get('run_interval') * 2 * 1000
        ) < DaemonUtil::timestampWithMilliseconds();
    }

    public function hasBeenDisabled()
    {
        return $this->get('disabled') === 'y';
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        if ($this->get('ts_last_attempt') === null) {
            return $this->isWithinTimeperiod();
        }

        if (
            $this->get('ts_last_attempt') + $this->get('run_interval') * 1000 < DaemonUtil::timestampWithMilliseconds()
        ) {
            return $this->isWithinTimeperiod();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isWithinTimeperiod()
    {
        if ($this->hasTimeperiod()) {
            return $this->timeperiod()->isActive();
        } else {
            return true;
        }
    }

    public function lastAttemptSucceeded()
    {
        return $this->get('last_attempt_succeeded') === 'y';
    }

    public function lastAttemptFailed()
    {
        return $this->get('last_attempt_succeeded') === 'n';
    }

    public function hasTimeperiod()
    {
        return $this->get('timeperiod_id') !== null;
    }

    /**
     * @param $timeperiod
     * @return $this
     * @throws \Icinga\Exception\NotFoundError
     */
    public function setTimeperiod($timeperiod)
    {
        if (is_string($timeperiod)) {
            $timeperiod = IcingaTimePeriod::load($timeperiod, $this->connection);
        } elseif (! $timeperiod instanceof IcingaTimePeriod) {
            throw new InvalidArgumentException('TimePeriod expected');
        }

        $this->set('timeperiod_id', $timeperiod->get('id'));

        return $this;
    }

    /**
     * @param string $name
     * @param int $id
     * @param Db $connection
     * @api internal
     * @return bool
     */
    protected static function existsWithNameAndId($name, $id, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $dummy = new static();
        $idCol = $dummy->autoincKeyName;
        $keyCol = $dummy->keyName;

        return (string) $id === (string) $db->fetchOne(
            $db->select()
                ->from($dummy->table, $idCol)
                ->where("$idCol = ?", $id)
                ->where("$keyCol = ?", $name)
        );
    }

    /**
     * @api internal Exporter only
     * @return IcingaTimePeriod
     */
    public function timeperiod()
    {
        try {
            return IcingaTimePeriod::loadWithAutoIncId($this->get('timeperiod_id'), $this->connection);
        } catch (NotFoundError $e) {
            throw new \RuntimeException(sprintf(
                'The TimePeriod configured for Job "%s" could not have been found',
                $this->get('name')
            ));
        }
    }
}
