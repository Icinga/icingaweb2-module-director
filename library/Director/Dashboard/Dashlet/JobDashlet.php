<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Exception;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Objects\DirectorJob;

class JobDashlet extends Dashlet
{
    protected $icon = 'clock';

    public function getTitle()
    {
        return $this->translate('Jobs');
    }

    public function listCssClasses()
    {
        try {
            return $this->fetchStateClass();
        } catch (Exception $e) {
            return 'state-critical';
        }
    }

    public function getSummary()
    {
        return $this->translate(
            'Schedule and automate Import, Syncronization, Config Deployment,'
            . ' Housekeeping and more'
        );
    }

    protected function fetchStateClass()
    {
        /** @var DirectorJob[] $jobs */
        $jobs = DirectorJob::loadAll($this->db);
        if (count($jobs) > 0) {
            $state = 'state-ok';
        } else {
            $state = null;
        }

        foreach ($jobs as $job) {
            if ($job->isPending()) {
                $state = 'state-pending';
            } elseif (! $job->lastAttemptSucceeded()) {
                $state = 'state-critical';
                break;
            }
        }

        return $state;
    }

    public function getUrl()
    {
        return 'director/jobs';
    }

    public function listRequiredPermissions()
    {
        return [Permission::ADMIN];
    }
}
