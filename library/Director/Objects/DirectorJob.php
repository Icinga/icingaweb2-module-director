<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Hook\JobHook;
use Exception;

class DirectorJob extends DbObjectWithSettings
{
    protected $job;

    protected $table = 'director_job';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
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
    );

    protected $settingsTable = 'director_job_setting';

    protected $settingsRemoteId = 'job_id';

    public function job()
    {
        if ($this->job === null) {
            $class = $this->job_class;
            $this->job = new $class;
            $this->job->setDb($this->connection);
        }

        return $this->job;
    }

    public function run()
    {
        $job = $this->job()->setDefinition($this);
        $this->ts_last_attempt = date('Y-m-d H:i:s');

        try {
            $job->run();
            $this->last_attempt_succeeded = 'y';
        } catch (Exception $e) {
            $this->ts_last_error = date('Y-m-d H:i:s');
            $this->last_error_message = $e->getMessage();
            $this->last_attempt_succeeded = 'n';
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }
    }

    public function shouldRun()
    {
        return (! $this->hasBeenDisabled()) && $this->isPending();
    }

    public function hasBeenDisabled()
    {
        return $this->disabled === 'y';
    }

    public function isPending()
    {
        if ($this->ts_last_attempt === null) {
            return $this->isWithinTimeperiod();
        }

        if (strtotime($this->ts_last_attempt) + $this->run_interval < time()) {
            return $this->isWithinTimeperiod();
        }

        return false;
    }

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
        return $this->last_attempt_succeeded === 'y';
    }

    public function hasTimeperiod()
    {
        return $this->timeperiod_id !== null;
    }

    protected function timeperiod()
    {
        return IcingaTimePeriod::loadWithAutoIncId($this->timeperiod_id, $this->connection);
    }
}
