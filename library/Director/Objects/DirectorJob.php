<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\NotFoundError;
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
            $this->job = new $class;
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
        $this->set('ts_last_attempt', date('Y-m-d H:i:s'));

        try {
            $job->run();
            $this->set('last_attempt_succeeded', 'y');
            $success = true;
        } catch (Exception $e) {
            Logger::error($e->getMessage());
            $this->set('ts_last_error', date('Y-m-d H:i:s'));
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

        return (
            strtotime((int) $this->get('ts_last_attempt')) + $this->get('run_interval') * 2
        ) < time();
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

        if (strtotime($this->get('ts_last_attempt')) + $this->get('run_interval') < time()) {
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
     * @return object
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        $plain = (object) $this->getProperties();
        $plain->originalId = $plain->id;
        unset($plain->id);
        unset($plain->timeperiod_id);
        if ($this->hasTimeperiod()) {
            $plain->timeperiod = $this->timeperiod()->getObjectName();
        }

        foreach ($this->stateProperties as $key) {
            unset($plain->$key);
        }
        $plain->settings = $this->getInstance()->exportSettings();

        return $plain;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return DirectorJob
     * @throws DuplicateKeyException
     * @throws NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $dummy = new static;
        $idCol = $dummy->autoincKeyName;
        $keyCol = $dummy->keyName;
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            $id = $properties['originalId'];
            unset($properties['originalId']);
        } else {
            $id = null;
        }
        $name = $properties[$keyCol];

        if ($replace && $id && static::existsWithNameAndId($name, $id, $db)) {
            $object = static::loadWithAutoIncId($id, $db);
        } elseif ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::exists($name, $db)) {
            throw new DuplicateKeyException(
                'Director Job "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        $settings = (array) $properties['settings'];

        if (array_key_exists('source', $settings) && ! (array_key_exists('source_id', $settings))) {
            $val = ImportSource::load($settings['source'], $db)->get('id');
            $settings['source_id'] = $val;
            unset($settings['source']);
        }

        if (array_key_exists('rule', $settings) && ! (array_key_exists('rule_id', $settings))) {
            $val = SyncRule::load($settings['rule'], $db)->get('id');
            $settings['rule_id'] = $val;
            unset($settings['rule']);
        }

        $properties['settings'] = (object) $settings;
        $object->setProperties($properties);
        if ($id !== null) {
            $object->reallySet($idCol, $id);
        }

        return $object;
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
        $dummy = new static;
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
