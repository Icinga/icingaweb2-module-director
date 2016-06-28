<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Objects\Job;
use Exception;

class JobTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                     => 'j.id',
            'job_name'               => 'j.job_name',
            'job_class'              => 'j.job_class',
            'disabled'               => 'j.disabled',
            'run_interval'           => 'j.run_interval',
            'last_attempt_succeeded' => 'j.last_attempt_succeeded',
            'ts_last_attempt'        => 'j.ts_last_attempt',
            'unixts_last_attempt'    => 'UNIX_TIMESTAMP(j.ts_last_attempt)',
            'ts_last_error'          => 'j.ts_last_error',
            'last_error_message'     => 'j.last_error_message',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/job', array('id' => $row->id));
    }

    protected function listTableClasses()
    {
        return array_merge(array('jobs'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        if ($row->unixts_last_attempt === null) {
            return 'pending';
        }

        if ($row->last_attempt_succeeded === 'n' && $row->last_error_message) {
            $row->job_name .= ' (' . $row->last_error_message . ')';
        }

        if ($row->unixts_last_attempt + $row->run_interval < time()) {
            return 'pending';
        }

        if ($row->last_attempt_succeeded === 'y') {
            return 'ok';
        } elseif ($row->last_attempt_succeeded === 'n') {
            return 'critical';
        } else {
            return 'unknown';
        }
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'job_name' => $view->translate('Job name'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('j' => 'director_job'),
            array()
        )->order('job_name');

        return $query;
    }
}
