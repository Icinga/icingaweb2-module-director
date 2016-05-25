<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Hook\JobHook;

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
        $this->job()->setDefinition($this)->run();
    }

    public function isPending()
    {
        if ($this->ts_last_attempt === null) {
            return $this->isWithinTimeperiod();
        }

        if (strtotime($this->unixts_last_attempt) + $this->run_interval < time()) {
            return $this->isWithinTimeperiod();
        }

        return false;
    }

    public function isWithinTimeperiod()
    {
        if ($this->hasTimeperiod()) {
            if (! $this->timeperiod()->isActive()) {
                return false;
            }
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
        return IcingaTimeperiod::load($this->timeperiod_id, $this->db);
    }
}
