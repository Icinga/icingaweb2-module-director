<?php

namespace Icinga\Module\Director;

use Icinga\Application\Config;
use Icinga\Date\DateFormatter;
use Icinga\Module\Director\CheckPlugin\Check;
use Icinga\Module\Director\CheckPlugin\CheckResults;
use Icinga\Module\Director\Db\Migrations;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Exception;

class Health
{
    /** @var Db */
    protected $connection;

    /** @var string */
    protected $dbResourceName;

    protected $checks = [
        'config'     => 'checkConfig',
        'sync'       => 'checkSyncRules',
        'import'     => 'checkImportSources',
        'jobs'       => 'checkDirectorJobs',
        'deployment' => 'checkDeployments',
    ];

    public function setDbResourceName($name)
    {
        $this->dbResourceName = $name;

        return $this;
    }

    public function getCheck(string $name, ?string $checkName = null): CheckResults
    {
        if (! array_key_exists($name, $this->checks)) {
            return (new CheckResults('Invalid Parameter'))
                ->fail("There is no check named '$name'");
        }

        if ($checkName !== null && ($name === 'deployment' || $name === 'config')) {
            return (new CheckResults('Invalid Parameter'))
                ->fail('--name is not supported with --check deployment or --check config');
        }

        $func = $this->checks[$name];
        if ($checkName !== null) {
            $check = $this->$func($checkName);
        } else {
            $check = $this->$func();
        }

        return $check;
    }

    public function getAllChecks()
    {
        /** @var CheckResults[] $checks */
        $checks = [$this->checkConfig()];

        if ($checks[0]->hasErrors()) {
            return $checks;
        }

        $checks[] = $this->checkDeployments();
        $checks[] = $this->checkImportSources();
        $checks[] = $this->checkSyncRules();
        $checks[] = $this->checkDirectorJobs();

        return $checks;
    }

    protected function hasDeploymentEndpoint()
    {
        try {
            return $this->connection->hasDeploymentEndpoint();
        } catch (Exception $e) {
            return false;
        }
    }

    public function hasResourceConfig()
    {
        return $this->getDbResourceName() !== null;
    }

    protected function getDbResourceName()
    {
        if ($this->dbResourceName === null) {
            $this->dbResourceName = Config::module('director')->get('db', 'resource');
        }

        return $this->dbResourceName;
    }

    protected function getConnection()
    {
        if ($this->connection === null) {
            $this->connection = Db::fromResourceName($this->getDbResourceName());
        }

        return $this->connection;
    }

    public function checkConfig()
    {
        $check = new Check('Director configuration');
        $name = $this->getDbResourceName();
        if ($name) {
            $check->succeed("Database resource '$name' has been specified");
        } else {
            return $check->fail('No database resource has been specified');
        }

        try {
            $db = $this->getConnection();
        } catch (Exception $e) {
            return $check->fail($e);
        }

        $migrations = new Migrations($db);
        $check->assertTrue(
            [$migrations, 'hasSchema'],
            'Make sure the DB schema exists'
        );

        if ($check->hasProblems()) {
            return $check;
        }

        $check->call(function () use ($check, $migrations) {
            $count = $migrations->countPendingMigrations();

            if ($count === 0) {
                $check->succeed('There are no pending schema migrations');
            } elseif ($count === 1) {
                $check->warn('There is a pending schema migration');
            } else {
                $check->warn(sprintf(
                    'There are %s pending schema migrations',
                    $count
                ));
            }
        });

        return $check;
    }

    public function checkSyncRules(?string $checkName = null): CheckResults
    {
        $check = new CheckResults('Sync Rules');
        if ($checkName !== null) {
            $rules = [SyncRule::load($checkName, $this->getConnection())];
        } else {
            $rules = SyncRule::loadAll($this->getConnection(), null, 'rule_name');
            if (empty($rules)) {
                $check->succeed('No Sync Rules have been defined');
                return $check;
            }

            ksort($rules);
        }

        foreach ($rules as $rule) {
            $state = $rule->get('sync_state');
            $name = $rule->get('rule_name');
            if ($state === 'failing') {
                $message = $rule->get('last_error_message');
                $check->fail("'$name' is failing: $message");
            } elseif ($state === 'pending-changes') {
                $check->succeed("'$name' is fine, but there are pending changes");
            } elseif ($state === 'in-sync') {
                $check->succeed("'$name' is in sync");
            } else {
                $check->fail("'$name' has never been checked", 'UNKNOWN');
            }
        }

        return $check;
    }

    public function checkImportSources(?string $checkName = null): CheckResults
    {
        $check = new CheckResults('Import Sources');
        if ($checkName !== null) {
            $sources = [ImportSource::load($checkName, $this->getConnection())];
        } else {
            $sources = ImportSource::loadAll($this->getConnection(), null, 'source_name');
            if (empty($sources)) {
                $check->succeed('No Import Sources have been defined');
                return $check;
            }

            ksort($sources);
        }

        foreach ($sources as $src) {
            $state = $src->get('import_state');
            $name = $src->get('source_name');
            if ($state === 'failing') {
                $message = $src->get('last_error_message');
                $check->fail("'$name' is failing: $message");
            } elseif ($state === 'pending-changes') {
                $check->succeed("'$name' is fine, but there are pending changes");
            } elseif ($state === 'in-sync') {
                $check->succeed("'$name' is in sync");
            } else {
                $check->fail("'$name' has never been checked", 'UNKNOWN');
            }
        }

        return $check;
    }

    public function checkDirectorJobs(?string $checkName = null): CheckResults
    {
        $check = new CheckResults('Director Jobs');
        if ($checkName !== null) {
            $jobs = [DirectorJob::load($checkName, $this->getConnection())];
        } else {
            $jobs = DirectorJob::loadAll($this->getConnection(), null, 'job_name');
            if (empty($jobs)) {
                $check->succeed('No Jobs have been defined');
                return $check;
            }

            ksort($jobs);
        }

        foreach ($jobs as $job) {
            $name = $job->get('job_name');
            if ($job->hasBeenDisabled()) {
                $check->succeed("'$name' has been disabled");
            } elseif ($job->lastAttemptFailed()) {
                $message = $job->get('last_error_message');
                $check->fail("Last attempt for '$name' failed: $message");
            } elseif ($job->isOverdue()) {
                $check->fail("'$name' is overdue");
            } elseif ($job->shouldRun()) {
                $check->succeed("'$name' is fine, but should run now");
            } else {
                $check->succeed("'$name' is fine");
            }
        }

        return $check;
    }

    public function checkDeployments()
    {
        $check = new Check('Director Deployments');

        $db = $this->getConnection();

        $check->call(function () use ($check, $db) {
            $check->succeed(sprintf(
                "Deployment endpoint is '%s'",
                $db->getDeploymentEndpointName()
            ));
        })->call(function () use ($check, $db) {
            $count = $db->countActivitiesSinceLastDeployedConfig();

            if ($count === 1) {
                $check->succeed('There is a single un-deployed change');
            } else {
                $check->succeed(sprintf(
                    'There are %d un-deployed changes',
                    $count
                ));
            }
        });

        if (! DirectorDeploymentLog::hasDeployments($db)) {
            $check->warn('Configuration has never been deployed');
            return $check;
        }

        $latest = DirectorDeploymentLog::loadLatest($db);

        $ts = $latest->getDeploymentTimestamp();
        $time = DateFormatter::timeAgo($ts);
        if ($latest->succeeded()) {
            $check->succeed("The last Deployment was successful $time");
        } elseif ($latest->isPending()) {
            if ($ts + 180 < time()) {
                $check->warn("The last Deployment started $time and is still pending");
            } else {
                $check->succeed("The last Deployment started $time and is still pending");
            }
        } else {
            $check->fail("The last Deployment failed $time");
        }

        return $check;
    }

    public function __destruct()
    {
        if ($this->connection !== null) {
            // We created our own connection, so let's tear it down
            $this->connection->getDbAdapter()->closeConnection();
        }
    }
}
