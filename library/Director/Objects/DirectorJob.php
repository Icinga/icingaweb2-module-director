<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;

class DirectorJob extends DbObjectWithSettings
{
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
    );

    protected $settingsTable = 'director_job_setting';

    protected $settingsRemoteId = 'job_id';

    public function isPending()
    {
        if ($this->ts_last_attempt === null) {
            return true;
        }

        if (strtotime($this->unixts_last_attempt) + $this->run_interval < time()) {
            return true;
        }

        return false;
    }

    public function lastAttemptSucceeded()
    {
        return $this->last_attempt_succeeded === 'y';
    }
}
